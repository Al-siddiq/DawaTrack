<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

/**
 * Enforces the single Opening-stock invariant against a locked Stock Position.
 *
 * A fully reversed initial Opening restores eligibility because its evidence is
 * still retained while its net accountable effect is zero.
 */
final class OpeningEligibility
{
    public function __construct(private readonly BaseConnection $database)
    {
    }

    /** @param array<string, mixed> $position */
    public function assertEligible(array $position): void
    {
        if ((string) $position['expected_quantity'] !== '0.0000'
            || (string) $position['cost_basis_state'] !== 'UNESTABLISHED') {
            $this->reject();
        }

        $movements = $this->database->table('stock_movements')
            ->select('id,movement_type,source_kind,reversal_of_movement_id,quantity_delta')
            ->where('stock_position_id', $position['id'])
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($movements === []) {
            if ($position['last_movement_id'] !== null) {
                throw new ApiException('INVENTORY_STATE_CONFLICT', 'The Stock Position history is inconsistent.', 409);
            }

            return;
        }

        $last = $movements[array_key_last($movements)];
        if ((int) $position['last_movement_id'] !== (int) $last['id']) {
            throw new ApiException('INVENTORY_STATE_CONFLICT', 'The Stock Position history is inconsistent.', 409);
        }

        $openings = [];
        $reversed = [];
        foreach ($movements as $movement) {
            if ($movement['movement_type'] === 'OPENING' && $movement['source_kind'] === 'INTAKE_ITEM') {
                $openings[(int) $movement['id']] = (string) $movement['quantity_delta'];
                continue;
            }

            if ($movement['movement_type'] === 'REVERSAL' && $movement['source_kind'] === 'REVERSAL') {
                $originalId = (int) $movement['reversal_of_movement_id'];
                if (!isset($openings[$originalId]) || isset($reversed[$originalId])) {
                    $this->reject();
                }
                if ((string) $movement['quantity_delta'] !== $this->negatePositive($openings[$originalId])) {
                    throw new ApiException('INVENTORY_STATE_CONFLICT', 'The Opening reversal evidence is inconsistent.', 409);
                }
                $reversed[$originalId] = true;
                continue;
            }

            $this->reject();
        }

        if ($openings === [] || count($openings) !== count($reversed)) {
            $this->reject();
        }
    }

    private function negatePositive(string $quantity): string
    {
        return '-' . ltrim($quantity, '+');
    }

    private function reject(): never
    {
        throw new ApiException(
            'OPENING_ALREADY_ESTABLISHED',
            'Opening stock has already been established for this Product. Use Receive stock for later additions.',
            409,
        );
    }
}
