<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\FinancialMovement\Application\ExpenseService;
use App\Modules\FinancialMovement\Infrastructure\FinancialMovementQuery;
use Config\Services;

final class FinancialMovementController extends CommercialController
{
    private function service(): ExpenseService
    {
        return new ExpenseService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService(), Services::requestContext());
    }

    private function query(): FinancialMovementQuery
    {
        return new FinancialMovementQuery(db_connect(), $this->access());
    }

    public function history()
    {
        return $this->execute(fn($context) => $this->ok($this->query()->history($context, $this->request->getGet())));
    }

    public function show(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query()->show($context, $id)));
    }

    public function expensePreview()
    {
        return $this->execute(fn($context) => $this->ok($this->service()->preview($context, $this->input())));
    }

    public function expensePost()
    {
        return $this->execute(function ($context) {
            $id = $this->service()->post($context, $this->input());
            return $this->ok($this->query()->show($context, $id));
        });
    }
}
