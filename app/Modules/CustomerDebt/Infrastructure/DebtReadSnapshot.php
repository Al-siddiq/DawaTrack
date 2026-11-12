<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/** Count, totals and page are one consistent read; participates in an existing caller transaction. */
final class DebtReadSnapshot
{
    public function __construct(private readonly BaseConnection $database) {}

    public function run(callable $query): array
    {
        if ($this->database->transDepth > 0) {
            return $query();
        }
        $this->database->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $this->database->transException(true);
        if (!$this->database->transBegin()) {
            throw new \RuntimeException('Could not start the balance read.');
        }
        try {
            $result = $query();
            if (!$this->database->transCommit()) {
                throw new \RuntimeException('Could not finish the balance read.');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }
}
