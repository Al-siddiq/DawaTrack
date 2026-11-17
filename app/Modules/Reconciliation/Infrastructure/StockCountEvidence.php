<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Inventory\Application\StockCountAmount;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Frozen membership/cost provenance is bounded per-Product Audit, validated against count rows. */
final class StockCountEvidence
{
    public function __construct(private readonly BaseConnection $database) {}

    public function positions(ShopContext $context): array
    {
        $this->database->query('SELECT id FROM products WHERE tenant_id=? ORDER BY id FOR UPDATE', [$context->tenantId]);
        return $this->database->query(
            'SELECT sp.*,p.public_id product_public_id,p.name,p.unit_name FROM stock_positions sp JOIN products p ON p.tenant_id=sp.tenant_id AND p.id=sp.product_id WHERE sp.tenant_id=? AND sp.shop_id=? ORDER BY sp.product_id FOR UPDATE',
            [$context->tenantId, $context->shopId],
        )->getResultArray();
    }

    public static function fingerprint(array $rows): string
    {
        $values = [];
        foreach ($rows as $row) {
            $values[$row['product_public_id']] = [$row['product_public_id'], $row['name'], $row['unit_name'], (string) $row['expected_quantity'],
                (string) $row['expected_average_unit_cost'], (string) $row['cost_basis_state'], (string) $row['position_version']];
        }
        ksort($values, SORT_STRING);
        return hash('sha256', json_encode(array_values($values), JSON_THROW_ON_ERROR));
    }

    public function rows(ShopContext $context, array $control, bool $lock = false): array
    {
        $manifest = $this->manifest($context, $control);
        // Lock count/Product rows separately from Audit. No generation lock is acquired by this query.
        $rows = $this->database->query(
            'SELECT c.*,p.public_id product_public_id FROM reconciliation_stock_counts c JOIN products p ON p.tenant_id=c.tenant_id AND p.id=c.product_id WHERE c.tenant_id=? AND c.shop_id=? AND c.reconciliation_session_id=? ORDER BY p.public_id' . ($lock ? ' FOR UPDATE' : ''),
            [$context->tenantId, $context->shopId, $control['id']],
        )->getResultArray();
        $events = $this->database->query(
            "SELECT change_metadata FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code='reconciliation.stock_expected' AND category='RECONCILIATION' AND caused_by_audit_event_id=? ORDER BY id",
            [$context->tenantId, $context->shopId, $control['public_id'], $manifest['root_id']],
        )->getResultArray();
        $rows = $this->enrich($rows, $events);
        if (count($rows) !== $manifest['count'] || !hash_equals($manifest['digest'], self::fingerprint($rows))) {
            $this->invalid();
        }
        return $rows;
    }

    /** Aggregate progress does not fetch actual quantities, costs or all Product rows. */
    public function progress(ShopContext $context, array $control): array
    {
        return $this->totals($context, $control, $this->manifest($context, $control));
    }

    /** A row save validates only its Product proof; commit/finalize still validate the full digest. */
    public function one(ShopContext $context, array $control, string $product, bool $lock = false): ?array
    {
        $manifest = $this->manifest($context, $control);
        $this->totals($context, $control, $manifest);
        $rows = $this->database->query(
            'SELECT c.*,p.public_id product_public_id FROM reconciliation_stock_counts c JOIN products p ON p.tenant_id=c.tenant_id AND p.id=c.product_id WHERE c.tenant_id=? AND c.shop_id=? AND c.reconciliation_session_id=? AND p.public_id=? ORDER BY c.id LIMIT 2' . ($lock ? ' FOR UPDATE' : ''),
            [$context->tenantId, $context->shopId, $control['id'], $product],
        )->getResultArray();
        if ($rows === []) {
            return null;
        }
        if (count($rows) !== 1) {
            $this->invalid();
        }
        return $this->enrich($rows, $this->events($context, $control, $manifest, $rows))[0];
    }

    /** Search only captured names/units, and fetch at most one approved page. */
    public function page(ShopContext $context, array $control, string $search, int $page, int $perPage): array
    {
        if ($page < 1 || $perPage < 1 || $perPage > 50 || mb_strlen($search) > 100) {
            throw new ApiException('VALIDATION_FAILED', 'Use a short item name and a valid page.', 422);
        }
        $manifest = $this->manifest($context, $control);
        $totals = $this->totals($context, $control, $manifest);
        $from = ' FROM reconciliation_stock_counts c JOIN products p ON p.tenant_id=c.tenant_id AND p.id=c.product_id';
        $where = ' WHERE c.tenant_id=? AND c.shop_id=? AND c.reconciliation_session_id=?';
        $bind = [$context->tenantId, $context->shopId, $control['id']];
        if ($search !== '') {
            $from .= " JOIN audit_events a ON a.tenant_id=c.tenant_id AND a.shop_id=c.shop_id AND a.resource_type='reconciliation_session' AND a.resource_public_id=? AND a.action_code='reconciliation.stock_expected' AND a.category='RECONCILIATION' AND a.caused_by_audit_event_id=? AND JSON_UNQUOTE(JSON_EXTRACT(a.change_metadata,'$.product_public_id'))=p.public_id";
            $where .= " AND LOCATE(LOWER(?),LOWER(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(a.change_metadata,'$.name')),' ',JSON_UNQUOTE(JSON_EXTRACT(a.change_metadata,'$.unit_name')))))>0";
            $bind = [$control['public_id'], $manifest['root_id'], ...$bind, $search];
            $total = (int) $this->database->query('SELECT COUNT(*) AS total' . $from . $where, $bind)->getRowArray()['total'];
        } else {
            $total = $totals['total'];
        }
        $rows = $this->database->query('SELECT c.*,p.public_id product_public_id' . $from . $where . ' ORDER BY p.public_id LIMIT ? OFFSET ?',
            [...$bind, $perPage, ($page - 1) * $perPage])->getResultArray();
        $rows = $this->enrich($rows, $this->events($context, $control, $manifest, $rows));
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * v2: API count writes append a unique Audit id under the owning session lock.
     * No-op retries append nothing. Only count-write ids and entered counts form the token; no expected data enters it.
     * The full immutable dataset/digest is still checked in the commit transaction.
     */
    public function commitRevision(ShopContext $context, array $control, ?array $cash, ?array $progress = null): string
    {
        $manifest = $this->manifest($context, $control);
        $progress ??= $this->totals($context, $control, $manifest);
        $row = $this->database->query(
            "SELECT COALESCE(MAX(id),0) AS latest_count_id FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND category='RECONCILIATION' AND action_code IN ('reconciliation.stock_counted','reconciliation.control_counted')",
            [$context->tenantId, $context->shopId, $control['public_id']],
        )->getRowArray();
        return hash('sha256', json_encode(['stock-count-set-v2', $control['public_id'], $control['scope'],
            (string) $row['latest_count_id'], $progress['total'], $progress['counted'], $cash ? [$cash['actual_amount'], $cash['updated_at']] : null], JSON_THROW_ON_ERROR));
    }

    private function manifest(ShopContext $context, array $control): array
    {
        $action = $control['scope'] === 'CASH' ? 'reconciliation.cash_started' : 'reconciliation.control_started';
        $roots = $this->database->query(
            "SELECT id,change_metadata FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code=? AND category='RECONCILIATION'",
            [$context->tenantId, $context->shopId, $control['public_id'], $action],
        )->getResultArray();
        if (count($roots) !== 1) {
            $this->invalid();
        }
        $metadata = json_decode((string) $roots[0]['change_metadata'], true);
        if (!is_array($metadata) || !is_int($metadata['stock_count'] ?? null) || $metadata['stock_count'] < 0
            || !is_string($metadata['stock_digest'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $metadata['stock_digest'])
            || ($metadata['scope'] ?? null) !== $control['scope'] || ($metadata['cutoff_at'] ?? null) !== $control['cutoff_at']
            || ($metadata['generation_uuid'] ?? null) !== $control['generation_uuid']) {
            $this->invalid();
        }
        return ['root_id' => $roots[0]['id'], 'count' => $metadata['stock_count'], 'digest' => $metadata['stock_digest']];
    }

    private function totals(ShopContext $context, array $control, array $manifest): array
    {
        $counts = $this->database->query('SELECT COUNT(*) AS total,COALESCE(SUM(actual_quantity IS NOT NULL),0) AS counted FROM reconciliation_stock_counts WHERE tenant_id=? AND shop_id=? AND reconciliation_session_id=?',
            [$context->tenantId, $context->shopId, $control['id']])->getRowArray();
        $audit = $this->database->query("SELECT COUNT(*) AS total FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code='reconciliation.stock_expected' AND category='RECONCILIATION' AND caused_by_audit_event_id=?",
            [$context->tenantId, $context->shopId, $control['public_id'], $manifest['root_id']])->getRowArray();
        $total = (int) $counts['total'];
        $counted = (int) $counts['counted'];
        if ($total !== $manifest['count'] || (int) $audit['total'] !== $total || $counted < 0 || $counted > $total) {
            $this->invalid();
        }
        return ['total' => $total, 'counted' => $counted, 'all_counted' => $total === $counted];
    }

    private function events(ShopContext $context, array $control, array $manifest, array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_column($rows, 'product_public_id');
        if (count(array_unique($ids)) !== count($ids)) {
            $this->invalid();
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        return $this->database->query(
            "SELECT change_metadata FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code='reconciliation.stock_expected' AND category='RECONCILIATION' AND caused_by_audit_event_id=? AND JSON_UNQUOTE(JSON_EXTRACT(change_metadata,'$.product_public_id')) IN ($marks) ORDER BY id LIMIT ?",
            [$context->tenantId, $context->shopId, $control['public_id'], $manifest['root_id'], ...$ids, count($ids) + 1],
        )->getResultArray();
    }

    private function enrich(array $rows, array $events): array
    {
        if (count($rows) !== count($events)) {
            $this->invalid();
        }
        $map = [];
        foreach ($events as $event) {
            $value = json_decode((string) $event['change_metadata'], true);
            $id = is_array($value) ? ($value['product_public_id'] ?? '') : '';
            if (!is_string($id) || !\App\Support\Uuid::isV4($id) || isset($map[$id])) {
                $this->invalid();
            }
            $map[$id] = $value;
        }
        foreach ($rows as &$row) {
            $expected = $map[$row['product_public_id']] ?? null;
            if (!$expected || ($expected['expected_quantity'] ?? null) !== (string) $row['expected_quantity']
                || ($expected['expected_average_unit_cost'] ?? null) !== (string) $row['expected_average_unit_cost']
                || !in_array($expected['cost_basis_state'] ?? null, ['VALID', 'RETAINED', 'UNESTABLISHED'], true)
                || !is_string($expected['position_version'] ?? null) || !is_string($expected['name'] ?? null)
                || !is_string($expected['unit_name'] ?? null)) {
                $this->invalid();
            }
            $row['name'] = $expected['name'];
            $row['unit_name'] = $expected['unit_name'];
            $row['cost_basis_state'] = $expected['cost_basis_state'];
            $row['position_version'] = $expected['position_version'];
            StockCountAmount::signed((string) $row['expected_quantity']);
            if ($row['actual_quantity'] !== null && StockCountAmount::difference((string) $row['actual_quantity'], (string) $row['expected_quantity']) !== (string) $row['variance_quantity']) {
                $this->invalid();
            }
        }
        unset($row);
        if (count($map) !== count($rows)) {
            $this->invalid();
        }
        return $rows;
    }

    /** Legacy v1 helper retained for source compatibility; active endpoints issue commitRevision v2. */
    public static function revision(string $controlId, array $rows, ?array $cash): string
    {
        $values = [];
        foreach ($rows as $row) {
            $values[$row['product_public_id']] = [$row['product_public_id'], $row['actual_quantity'], $row['updated_at']];
        }
        ksort($values, SORT_STRING);
        return hash('sha256', json_encode([$controlId, array_values($values), $cash ? [$cash['actual_amount'], $cash['updated_at']] : null], JSON_THROW_ON_ERROR));
    }

    private function invalid(): never
    {
        throw new ApiException('STOCK_CONTROL_EVIDENCE_INVALID', 'The saved item list and counts do not match. Keep them as they are and ask the shop owner for help.', 409);
    }
}
