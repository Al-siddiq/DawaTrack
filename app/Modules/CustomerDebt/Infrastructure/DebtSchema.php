<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

/** One explicit rollout gate; absence never means zero collections. */
final class DebtSchema
{
    public function __construct(private readonly BaseConnection $database) {}

    public function requireReady(): void
    {
        foreach (['debt_payments', 'debt_payment_allocations'] as $table) {
            if (!$this->database->tableExists($table)) {
                throw new ApiException(
                    'DEBT_SETUP_REQUIRED',
                    'Customer balances are not ready. Ask the shop owner to complete the application update.',
                    503,
                );
            }
        }
    }
}
