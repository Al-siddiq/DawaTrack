<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use CodeIgniter\Database\BaseConnection;

/** Read-only consistency diagnostics for one explicit Tenant/Shop scope. */
final class InventoryDiagnostics
{
    private const SAMPLE_LIMIT = 100;

    public function __construct(private readonly BaseConnection $database)
    {
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function inspect(int $tenantId, int $shopId): array
    {
        $scope = [$tenantId, $shopId];

        return [
            'position_quantity' => $this->rows(<<<'SQL'
SELECT sp.id position_id, p.public_id product_public_id, sp.expected_quantity,
       COALESCE(SUM(m.quantity_delta), 0.0000) movement_quantity
FROM stock_positions sp
JOIN products p ON p.tenant_id=sp.tenant_id AND p.id=sp.product_id
LEFT JOIN stock_movements m ON m.tenant_id=sp.tenant_id AND m.shop_id=sp.shop_id AND m.stock_position_id=sp.id
WHERE sp.tenant_id=? AND sp.shop_id=?
GROUP BY sp.id,p.public_id,sp.expected_quantity
HAVING sp.expected_quantity <> movement_quantity
ORDER BY sp.id LIMIT 100
SQL, $scope),
            'last_pointer' => $this->rows(<<<'SQL'
SELECT sp.id position_id, p.public_id product_public_id, sp.last_movement_id, MAX(m.id) latest_movement_id
FROM stock_positions sp
JOIN products p ON p.tenant_id=sp.tenant_id AND p.id=sp.product_id
LEFT JOIN stock_movements m ON m.tenant_id=sp.tenant_id AND m.shop_id=sp.shop_id AND m.stock_position_id=sp.id
WHERE sp.tenant_id=? AND sp.shop_id=?
GROUP BY sp.id,p.public_id,sp.last_movement_id
HAVING NOT (sp.last_movement_id <=> MAX(m.id))
ORDER BY sp.id LIMIT 100
SQL, $scope),
            'snapshot_chain' => $this->rows(<<<'SQL'
WITH chain AS (
 SELECT m.id,m.public_id,m.stock_position_id,m.quantity_before,m.average_cost_before,
        LAG(m.quantity_after) OVER (PARTITION BY m.stock_position_id ORDER BY m.id) previous_quantity,
        LAG(m.average_cost_after) OVER (PARTITION BY m.stock_position_id ORDER BY m.id) previous_average
 FROM stock_movements m WHERE m.tenant_id=? AND m.shop_id=?
)
SELECT id movement_id,public_id movement_public_id,stock_position_id,quantity_before,previous_quantity,average_cost_before,previous_average
FROM chain WHERE (previous_quantity IS NOT NULL AND quantity_before<>previous_quantity)
   OR (previous_average IS NOT NULL AND average_cost_before<>previous_average)
ORDER BY id LIMIT 100
SQL, $scope),
            'source_or_sign' => $this->rows(<<<'SQL'
SELECT id movement_id,public_id movement_public_id,movement_type,source_kind,quantity_delta
FROM stock_movements
WHERE tenant_id=? AND shop_id=? AND (
 movement_type NOT IN ('OPENING','STOCK_INTAKE','DAMAGED','EXPIRED','OWNER_WITHDRAWAL','MISSING_LOSS','CORRECTION','REVERSAL')
 OR source_kind NOT IN ('INTAKE_ITEM','MANUAL','REVERSAL')
 OR (movement_type IN ('OPENING','STOCK_INTAKE') AND (source_kind<>'INTAKE_ITEM' OR quantity_delta<=0))
 OR (movement_type IN ('DAMAGED','EXPIRED','OWNER_WITHDRAWAL','MISSING_LOSS') AND (source_kind<>'MANUAL' OR quantity_delta>=0))
 OR (movement_type='CORRECTION' AND source_kind<>'MANUAL')
 OR (movement_type='REVERSAL' AND source_kind<>'REVERSAL')
)
ORDER BY id LIMIT 100
SQL, $scope),
            'intake_item_coverage' => $this->rows(<<<'SQL'
SELECT i.id item_id,i.public_id item_public_id,si.public_id intake_public_id,COUNT(m.id) movement_count
FROM stock_intake_items i
JOIN stock_intakes si ON si.tenant_id=i.tenant_id AND si.shop_id=i.shop_id AND si.id=i.stock_intake_id
LEFT JOIN stock_movements m ON m.tenant_id=i.tenant_id AND m.shop_id=i.shop_id AND m.stock_intake_item_id=i.id
WHERE i.tenant_id=? AND i.shop_id=? AND si.status='POSTED'
GROUP BY i.id,i.public_id,si.public_id HAVING movement_count<>1
ORDER BY i.id LIMIT 100
SQL, $scope),
            'reversal_integrity' => $this->rows(<<<'SQL'
SELECT r.id reversal_id,r.public_id reversal_public_id,o.public_id original_public_id
FROM stock_movements r
LEFT JOIN stock_movements o ON o.tenant_id=r.tenant_id AND o.shop_id=r.shop_id AND o.id=r.reversal_of_movement_id
WHERE r.tenant_id=? AND r.shop_id=? AND r.source_kind='REVERSAL' AND (
 o.id IS NULL OR o.source_kind='REVERSAL' OR r.product_id<>o.product_id OR r.stock_position_id<>o.stock_position_id
 OR r.quantity_delta<>-o.quantity_delta
)
ORDER BY r.id LIMIT 100
SQL, $scope),
            'intake_reversal_graph' => $this->rows(<<<'SQL'
SELECT b.id reversal_intake_id,b.public_id reversal_public_id,a.public_id original_public_id,
       COUNT(bi.id) reversal_item_count
FROM stock_intakes b
LEFT JOIN stock_intakes a ON a.tenant_id=b.tenant_id AND a.shop_id=b.shop_id AND a.id=b.reversal_of_intake_id
LEFT JOIN stock_intake_items bi ON bi.tenant_id=b.tenant_id AND bi.shop_id=b.shop_id AND bi.stock_intake_id=b.id
WHERE b.tenant_id=? AND b.shop_id=? AND b.reversal_of_intake_id IS NOT NULL
GROUP BY b.id,b.public_id,a.public_id,a.status,a.intake_kind,b.status,b.intake_kind
HAVING a.public_id IS NULL OR a.status<>'POSTED' OR b.status<>'POSTED' OR a.intake_kind<>b.intake_kind OR reversal_item_count<>0
ORDER BY b.id LIMIT 100
SQL, $scope),
            'replacement_graph' => $this->rows(<<<'SQL'
SELECT c.id replacement_intake_id,c.public_id replacement_public_id,a.public_id original_public_id,
       b.public_id reversal_public_id,c.status replacement_status
FROM stock_intakes c
LEFT JOIN stock_intakes a ON a.tenant_id=c.tenant_id AND a.shop_id=c.shop_id AND a.id=c.replacement_for_intake_id
LEFT JOIN stock_intakes b ON b.tenant_id=a.tenant_id AND b.shop_id=a.shop_id AND b.reversal_of_intake_id=a.id
WHERE c.tenant_id=? AND c.shop_id=? AND c.replacement_for_intake_id IS NOT NULL AND (
 a.id IS NULL OR a.status<>'POSTED' OR b.id IS NULL OR b.status<>'POSTED' OR c.id=a.id
)
ORDER BY c.id LIMIT 100
SQL, $scope),
            'scope_integrity' => $this->rows(<<<'SQL'
SELECT m.id movement_id,m.public_id movement_public_id
FROM stock_movements m
LEFT JOIN stock_positions sp ON sp.id=m.stock_position_id
LEFT JOIN products p ON p.id=m.product_id
WHERE m.tenant_id=? AND m.shop_id=? AND (
 sp.id IS NULL OR p.id IS NULL OR sp.tenant_id<>m.tenant_id OR sp.shop_id<>m.shop_id
 OR sp.product_id<>m.product_id OR p.tenant_id<>m.tenant_id
)
ORDER BY m.id LIMIT 100
SQL, $scope),
            'version_anomaly' => $this->rows(<<<'SQL'
SELECT sp.id position_id,p.public_id product_public_id,sp.version,COUNT(m.id) movement_count
FROM stock_positions sp
JOIN products p ON p.tenant_id=sp.tenant_id AND p.id=sp.product_id
LEFT JOIN stock_movements m ON m.tenant_id=sp.tenant_id AND m.shop_id=sp.shop_id AND m.stock_position_id=sp.id
WHERE sp.tenant_id=? AND sp.shop_id=?
GROUP BY sp.id,p.public_id,sp.version HAVING sp.version<movement_count
ORDER BY sp.id LIMIT 100
SQL, $scope),
        ];
    }

    /** @param list<int> $scope @return list<array<string, mixed>> */
    private function rows(string $sql, array $scope): array
    {
        return array_slice($this->database->query($sql, $scope)->getResultArray(), 0, self::SAMPLE_LIMIT);
    }
}
