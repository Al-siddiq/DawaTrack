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
        $action = $control['scope'] === 'CASH' ? 'reconciliation.cash_started' : 'reconciliation.control_started';
        $roots = $this->database->query(
            "SELECT id,change_metadata FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code=? AND category='RECONCILIATION'",
            [$context->tenantId, $context->shopId, $control['public_id'], $action],
        )->getResultArray();
        if (count($roots) !== 1) {
            $this->invalid();
        }
        $metadata = json_decode((string) $roots[0]['change_metadata'], true);
        if (!is_array($metadata) || !is_int($metadata['stock_count'] ?? null) || !is_string($metadata['stock_digest'] ?? null)) {
            $this->invalid();
        }
        // Lock count/Product rows separately from Audit. No generation lock is acquired by this query.
        $rows = $this->database->query(
            'SELECT c.*,p.public_id product_public_id FROM reconciliation_stock_counts c JOIN products p ON p.tenant_id=c.tenant_id AND p.id=c.product_id WHERE c.tenant_id=? AND c.shop_id=? AND c.reconciliation_session_id=? ORDER BY p.public_id' . ($lock ? ' FOR UPDATE' : ''),
            [$context->tenantId, $context->shopId, $control['id']],
        )->getResultArray();
        $events = $this->database->query(
            "SELECT change_metadata FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code='reconciliation.stock_expected' AND caused_by_audit_event_id=? ORDER BY id",
            [$context->tenantId, $context->shopId, $control['public_id'], $roots[0]['id']],
        )->getResultArray();
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
        if (count($rows) !== $metadata['stock_count'] || count($events) !== count($rows)
            || !hash_equals($metadata['stock_digest'], self::fingerprint($rows))) {
            $this->invalid();
        }
        return $rows;
    }

    /** Only actual observations and stored revisions enter the opaque whole-set commit revision. */
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
