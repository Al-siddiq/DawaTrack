<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;

final class DebtQuery
{
    private const MONEY_FIELDS = [
        'original_amount', 'adjustment_delta', 'adjusted_amount',
        'collected_amount', 'outstanding_amount', 'overdue_amount',
    ];

    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}

    public function customers(ShopContext $context, array $input): array
    {
        return $this->read($context, function (string $date, string $asOf) use ($context, $input): array {
            DraftInput::only($input, ['search', 'status', 'customer_status', 'page', 'per_page']);
            [$page, $size] = $this->paginationInput($input);
            $status = $this->status($input['status'] ?? 'OWING');
            $customerStatus = $input['customer_status'] ?? 'ALL';
            if (!in_array($customerStatus, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
                throw new ApiException('VALIDATION_FAILED', 'Choose which customers to show.', 422);
            }
            $search = DraftInput::text($input['search'] ?? '', 'search', 160);
            [$balances, $binds] = (new ReceivableBalanceQuery())->projection($context, $date);
            $where = 'c.tenant_id=?';
            $binds[] = $context->tenantId;
            if ($customerStatus !== 'ALL') {
                $where .= ' AND c.status=?';
                $binds[] = $customerStatus;
            }
            if ($search !== null) {
                // Literal name search: never search private phone/notes or interpret user wildcards.
                $where .= ' AND LOCATE(?,c.display_name)>0';
                $binds[] = $search;
            }
            $grouped = "SELECT c.id,c.public_id,c.display_name,c.status customer_status,
                COUNT(b.id) bill_count,
                COALESCE(SUM(b.original_amount),0.00) original_amount,
                COALESCE(SUM(b.adjustment_delta),0.00) adjustment_delta,
                COALESCE(SUM(b.adjusted_amount),0.00) adjusted_amount,
                COALESCE(SUM(b.collected_amount),0.00) collected_amount,
                COALESCE(SUM(b.outstanding_amount),0.00) outstanding_amount,
                COALESCE(SUM(CASE WHEN b.is_overdue=1 THEN b.outstanding_amount ELSE 0.00 END),0.00) overdue_amount,
                SUM(CASE WHEN b.is_overdue=1 THEN 1 ELSE 0 END) overdue_bill_count,
                MIN(CASE WHEN b.outstanding_amount>0 THEN b.due_date ELSE NULL END) next_due_date,
                COALESCE(SUM(b.invalid_balance),0) invalid_balance
                FROM customers c LEFT JOIN ($balances) b ON b.customer_id=c.id
                WHERE $where GROUP BY c.id,c.public_id,c.display_name,c.status";
            $integrity = $this->database->query(
                "SELECT COALESCE(SUM(invalid_balance),0) invalid_balance FROM ($grouped) checked", $binds,
            )->getRowArray();
            $this->assertValid($integrity);
            $filtered = "SELECT g.* FROM ($grouped) g WHERE " . $this->filter($status, 'g');
            $summary = $this->database->query(
                "SELECT COUNT(*) customer_count,COALESCE(SUM(outstanding_amount),0.00) outstanding_amount,
                 COALESCE(SUM(overdue_amount),0.00) overdue_amount,COALESCE(SUM(invalid_balance),0) invalid_balance
                 FROM ($filtered) totals", $binds,
            )->getRowArray();
            $this->assertValid($summary);
            $rows = $this->database->query(
                "$filtered ORDER BY g.display_name,g.id LIMIT ? OFFSET ?",
                [...$binds, $size, ($page - 1) * $size],
            )->getResultArray();
            $items = [];
            foreach ($rows as $row) {
                $this->assertValid($row);
                $items[] = $this->amounts($row) + [
                    'public_id' => $row['public_id'], 'display_name' => $row['display_name'],
                    'customer_status' => $row['customer_status'], 'bill_count' => (int) $row['bill_count'],
                    'overdue_bill_count' => (int) $row['overdue_bill_count'],
                    'next_due_date' => $row['next_due_date'],
                    'settlement_state' => (int) $row['bill_count'] === 0 ? 'NO_DEBT'
                        : ((string) $row['outstanding_amount'] === '0.00' ? 'SETTLED'
                            : ((string) $row['collected_amount'] !== '0.00' ? 'PARTIAL' : 'OUTSTANDING')),
                ];
            }
            return [
                'items' => $items,
                'summary' => $this->amounts($summary) + ['customer_count' => (int) $summary['customer_count']],
                'pagination' => $this->pagination($page, $size, (int) $summary['customer_count']),
                'shop_date' => $date, 'as_of' => $asOf,
            ];
        });
    }

    public function customer(ShopContext $context, string $id, array $input): array
    {
        return $this->read($context, function (string $date, string $asOf) use ($context, $id, $input): array {
            DraftInput::only($input, ['status', 'page', 'per_page']);
            [$page, $size] = $this->paginationInput($input);
            $status = $this->status($input['status'] ?? 'ALL');
            $customer = $this->database->table('customers')
                ->select('id,public_id,display_name,status')
                ->where(['tenant_id' => $context->tenantId, 'public_id' => DraftInput::uuid($id)])
                ->get()->getRowArray();
            if (!$customer) {
                throw new ApiException('RESOURCE_NOT_FOUND', 'This customer was not found.', 404);
            }
            [$balances, $binds] = (new ReceivableBalanceQuery())->projection($context, $date);
            $allBills = "SELECT b.* FROM ($balances) b WHERE b.customer_id=?";
            $binds[] = $customer['id'];
            $summary = $this->database->query(
                "SELECT COUNT(*) bill_count,COALESCE(SUM(original_amount),0.00) original_amount,
                 COALESCE(SUM(adjustment_delta),0.00) adjustment_delta,COALESCE(SUM(adjusted_amount),0.00) adjusted_amount,
                 COALESCE(SUM(collected_amount),0.00) collected_amount,COALESCE(SUM(outstanding_amount),0.00) outstanding_amount,
                 COALESCE(SUM(CASE WHEN is_overdue=1 THEN outstanding_amount ELSE 0.00 END),0.00) overdue_amount,
                 COALESCE(SUM(invalid_balance),0) invalid_balance FROM ($allBills) totals", $binds,
            )->getRowArray();
            $this->assertValid($summary);
            $filtered = "$allBills AND " . $this->filter($status, 'b', true);
            $total = (int) $this->database->query("SELECT COUNT(*) total FROM ($filtered) counted", $binds)->getRowArray()['total'];
            $rows = $this->database->query(
                "SELECT f.*,s.public_id sale_public_id FROM ($filtered) f
                 JOIN sales s ON s.tenant_id=? AND s.shop_id=? AND s.id=f.sale_id
                 ORDER BY f.posted_at,f.id LIMIT ? OFFSET ?",
                [...$binds, $context->tenantId, $context->shopId, $size, ($page - 1) * $size],
            )->getResultArray();
            return [
                'customer' => array_intersect_key($customer, array_flip(['public_id', 'display_name', 'status'])),
                'summary' => $this->amounts($summary) + ['bill_count' => (int) $summary['bill_count']],
                'bills' => array_map(fn(array $row): array => $this->billOutput($row), $rows),
                'pagination' => $this->pagination($page, $size, $total),
                'shop_date' => $date, 'as_of' => $asOf,
            ];
        });
    }

    public function bill(ShopContext $context, string $id): array
    {
        return $this->read($context, function (string $date, string $asOf) use ($context, $id): array {
            [$balances, $binds] = (new ReceivableBalanceQuery())->projection($context, $date);
            $row = $this->database->query(
                "SELECT b.*,s.public_id sale_public_id,c.public_id customer_public_id,c.display_name customer_name
                 FROM ($balances) b
                 JOIN sales s ON s.tenant_id=? AND s.shop_id=? AND s.id=b.sale_id
                 JOIN customers c ON c.tenant_id=s.tenant_id AND c.id=b.customer_id
                 WHERE b.public_id=?",
                [...$binds, $context->tenantId, $context->shopId, DraftInput::uuid($id)],
            )->getRowArray();
            if (!$row) {
                throw new ApiException('RESOURCE_NOT_FOUND', 'This bill was not found.', 404);
            }
            return $this->billOutput($row) + [
                'customer_public_id' => $row['customer_public_id'], 'customer_name' => $row['customer_name'],
                'shop_date' => $date, 'as_of' => $asOf,
            ];
        });
    }

    private function read(ShopContext $context, callable $query): array
    {
        $this->access->read($context, 'customer.view');
        $this->access->read($context, 'debt.view');
        (new DebtSchema($this->database))->requireReady();
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $query): array {
            $shop = $this->database->table('shops')->select('timezone_name')
                ->where(['tenant_id' => $context->tenantId, 'id' => $context->shopId])->get()->getRowArray();
            if (!$shop) {
                throw new ApiException('RESOURCE_NOT_FOUND', 'This shop was not found.', 404);
            }
            $asOf = UtcTimestamp::now();
            $date = (new DateTimeImmutable($asOf, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone($shop['timezone_name']))->format('Y-m-d');
            return $query($date, $asOf);
        });
    }

    private function filter(string $status, string $alias, bool $bill = false): string
    {
        // Only closed server-owned strings enter SQL, never request input.
        return match ($status) {
            'OWING' => "$alias.outstanding_amount>0",
            'OUTSTANDING' => "$alias.outstanding_amount>0 AND $alias.collected_amount=0",
            'PARTIAL' => "$alias.outstanding_amount>0 AND $alias.collected_amount>0",
            'SETTLED' => "$alias.outstanding_amount=0" . ($bill ? '' : " AND $alias.bill_count>0"),
            'OVERDUE' => $bill ? "$alias.is_overdue=1" : "$alias.overdue_amount>0",
            default => '1=1',
        };
    }

    private function status(mixed $status): string
    {
        if (!in_array($status, ['ALL', 'OWING', 'OUTSTANDING', 'PARTIAL', 'SETTLED', 'OVERDUE'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose which bills or balances to show.', 422);
        }
        return $status;
    }

    private function paginationInput(array $input): array
    {
        $values = [];
        foreach (['page' => [1, 100000], 'per_page' => [20, 50]] as $key => [$default, $maximum]) {
            $value = $input[$key] ?? $default;
            if ((!is_string($value) && !is_int($value))
                || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]) === false) {
                throw new ApiException('VALIDATION_FAILED', 'Choose a valid page and number of records.', 422);
            }
            $values[] = (int) $value;
        }
        return $values;
    }

    private function pagination(int $page, int $size, int $total): array
    {
        return ['page' => $page, 'per_page' => $size, 'total' => $total,
            'total_pages' => (int) ceil($total / $size)];
    }

    private function assertValid(array $row): void
    {
        if ((int) ($row['invalid_balance'] ?? 0) > 0) {
            throw new ApiException('DEBT_EVIDENCE_INVALID', 'These balances need checking. Ask the shop owner for help.', 409);
        }
    }

    private function amounts(array $row): array
    {
        $out = [];
        foreach (self::MONEY_FIELDS as $field) {
            if (array_key_exists($field, $row)) {
                $out[$field] = (string) $row[$field];
            }
        }
        return $out;
    }

    private function billOutput(array $row): array
    {
        $this->assertValid($row);
        return $this->amounts($row) + [
            'public_id' => $row['public_id'], 'sale_public_id' => $row['sale_public_id'],
            'due_date' => $row['due_date'], 'occurred_at' => $row['occurred_at'], 'posted_at' => $row['posted_at'],
            'settlement_state' => $row['settlement_state'], 'is_overdue' => (bool) $row['is_overdue'],
        ];
    }
}
