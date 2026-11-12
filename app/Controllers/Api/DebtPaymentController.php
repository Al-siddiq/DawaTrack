<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\CustomerDebt\Application\DebtPaymentService;
use App\Modules\CustomerDebt\Infrastructure\DebtPaymentQuery;
use Config\Services;

final class DebtPaymentController extends CommercialController
{
    private function service(): DebtPaymentService
    {
        return new DebtPaymentService(db_connect(), $this->access(), Services::auditService(),
            Services::changeSequenceService(), Services::requestContext());
    }

    private function query(): DebtPaymentQuery
    {
        return new DebtPaymentQuery(db_connect(), $this->access());
    }

    public function preview()
    {
        return $this->execute(fn($context) => $this->ok($this->service()->preview($context, $this->input())));
    }

    public function post()
    {
        return $this->execute(function ($context) {
            $id = $this->service()->post($context, $this->input());
            return $this->ok($this->query()->show($context, $id));
        });
    }

    public function show(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query()->show($context, $id)));
    }

    public function history(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query()->history($context, $id, $this->request->getGet())));
    }
}
