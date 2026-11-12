<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\CustomerDebt\Infrastructure\DebtQuery;

/** Debt balances and statements are authorized GET-only read models. */
final class DebtController extends CommercialController
{
    private function query(): DebtQuery
    {
        return new DebtQuery(db_connect(), $this->access());
    }

    public function index()
    {
        return $this->execute(fn($context) => $this->ok(
            $this->query()->customers($context, $this->request->getGet()),
        ));
    }

    public function customer(string $id)
    {
        return $this->execute(fn($context) => $this->ok(
            $this->query()->customer($context, $id, $this->request->getGet()),
        ));
    }

    public function bill(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query()->bill($context, $id)));
    }
    public function statement(string $id)
    {
        return $this->execute(fn($context) => $this->ok(
            (new \App\Modules\CustomerDebt\Infrastructure\StatementQuery(db_connect(), $this->access()))
                ->show($context, $id, $this->request->getGet()),
        ));
    }
}
