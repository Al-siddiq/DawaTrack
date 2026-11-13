<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Infrastructure;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

/** Missing or partial rollout is never represented as an empty movement ledger. */
final class FinancialMovementSchema
{
    public function __construct(private readonly BaseConnection $database) {}

    public function requireReady(): void
    {
        $columns = $this->database->query(
            "SELECT column_name `name`,column_type `data_type`,is_nullable `nullable`,extra `extra`,character_set_name `charset`,collation_name `collation`
             FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='financial_movements'",
        )->getResultArray();
        $expected = ['id' => 'bigint unsigned', 'public_id' => 'char(36)', 'tenant_id' => 'bigint unsigned',
            'shop_id' => 'bigint unsigned', 'movement_type' => 'varchar(32)', 'channel' => 'varchar(12)',
            'amount_delta' => 'decimal(19,2)', 'description' => 'varchar(300)', 'external_reference' => 'varchar(120)',
            'reversal_of_movement_id' => 'bigint unsigned', 'replacement_for_movement_id' => 'bigint unsigned',
            'occurred_at' => 'datetime(6)', 'posted_at' => 'datetime(6)', 'created_by_user_id' => 'int unsigned',
            'correlation_id' => 'char(36)', 'created_at' => 'datetime(6)'];
        $nullable = ['external_reference', 'reversal_of_movement_id', 'replacement_for_movement_id', 'correlation_id'];
        $byName = array_column($columns, null, 'name');
        foreach ($expected as $name => $type) {
            $column = $byName[$name] ?? null;
            $actual = $column ? preg_replace('/\b(bigint|int)\([0-9]+\)/', '$1', strtolower($column['data_type'])) : null;
            if (!$column || $actual !== $type || ($column['nullable'] === 'YES') !== in_array($name, $nullable, true)) {
                $this->notReady();
            }
            if (in_array($name, ['public_id', 'correlation_id'], true) && ($column['charset'] !== 'ascii' || $column['collation'] !== 'ascii_bin')) {
                $this->notReady();
            }
        }
        if (!str_contains(strtolower($byName['id']['extra']), 'auto_increment')) {
            $this->notReady();
        }
        $indexes = $this->database->query(
            "SELECT index_name `name`,column_name `column_name`,non_unique `non_unique` FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='financial_movements' ORDER BY index_name,seq_in_index",
        )->getResultArray();
        $actualIndexes = [];
        foreach ($indexes as $index) {
            $actualIndexes[$index['name']]['columns'][] = $index['column_name'];
            $actualIndexes[$index['name']]['unique'] = (int) $index['non_unique'] === 0;
        }
        foreach (['PRIMARY' => [['id'], true], 'uq_financial_movements_public' => [['public_id'], true],
            'uq_financial_movements_scope' => [['tenant_id', 'shop_id', 'id'], true],
            'uq_financial_movements_reversal' => [['reversal_of_movement_id'], true],
            'ix_financial_movements_channel' => [['tenant_id', 'shop_id', 'channel', 'posted_at', 'id'], false],
            'ix_financial_movements_type' => [['tenant_id', 'shop_id', 'movement_type', 'posted_at'], false]] as $name => [$parts, $unique]) {
            if (($actualIndexes[$name]['columns'] ?? null) !== $parts || ($actualIndexes[$name]['unique'] ?? null) !== $unique) {
                $this->notReady();
            }
        }
        $constraints = $this->database->query(
            "SELECT constraint_name `name`,constraint_type `kind` FROM information_schema.table_constraints
             WHERE table_schema=DATABASE() AND table_name='financial_movements'",
        )->getResultArray();
        $kinds = array_column($constraints, 'kind', 'name');
        foreach (['uuid', 'channel', 'type', 'amount', 'sign', 'description', 'links'] as $suffix) {
            if (($kinds['ck_financial_movements_' . $suffix] ?? null) !== 'CHECK') {
                $this->notReady();
            }
        }
        $keys = $this->database->query(
            "SELECT constraint_name `name`,column_name source_column,referenced_table_name target_table,referenced_column_name target_column
             FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name='financial_movements'
             AND referenced_table_name IS NOT NULL ORDER BY constraint_name,ordinal_position",
        )->getResultArray();
        $actualKeys = [];
        foreach ($keys as $key) {
            $actualKeys[$key['name']][] = [$key['source_column'], $key['target_table'], $key['target_column']];
        }
        $scope = [['tenant_id', 'financial_movements', 'tenant_id'], ['shop_id', 'financial_movements', 'shop_id']];
        foreach (['fk_financial_movements_tenant' => [['tenant_id', 'tenants', 'id']],
            'fk_financial_movements_shop' => [['tenant_id', 'shops', 'tenant_id'], ['shop_id', 'shops', 'id']],
            'fk_financial_movements_actor' => [['created_by_user_id', 'users', 'id']],
            'fk_financial_movements_reversal' => [...$scope, ['reversal_of_movement_id', 'financial_movements', 'id']],
            'fk_financial_movements_replacement' => [...$scope, ['replacement_for_movement_id', 'financial_movements', 'id']]] as $name => $parts) {
            if (($kinds[$name] ?? null) !== 'FOREIGN KEY' || ($actualKeys[$name] ?? null) !== $parts) {
                $this->notReady();
            }
        }
    }

    private function notReady(): never
    {
        throw new ApiException('FINANCIAL_SETUP_REQUIRED', 'Money movements are not ready. Ask the shop owner to complete the application update.', 503);
    }
}
