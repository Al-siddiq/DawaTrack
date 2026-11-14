<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\FinancialMovement\Application\{ExpenseService, FinancialMovementCorrectionService, OwnerMovementService};
use App\Modules\FinancialMovement\Infrastructure\FinancialMovementQuery;
use Config\Services;

final class FinancialMovementController extends CommercialController
{
    private function service(): ExpenseService
    {
        return new ExpenseService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService(), Services::requestContext());
    }

    private function ownerService(): OwnerMovementService
    {
        return new OwnerMovementService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService(), Services::requestContext());
    }

    private function correctionService(): FinancialMovementCorrectionService
    {
        return new FinancialMovementCorrectionService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService(), Services::requestContext());
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

    public function correctionState(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query()->correctionState($context, $id)));
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

    public function withdrawalPreview()
    {
        return $this->execute(fn($context) => $this->ok($this->ownerService()->previewWithdrawal($context, $this->input())));
    }

    public function withdrawalPost()
    {
        return $this->execute(function ($context) {
            $id = $this->ownerService()->postWithdrawal($context, $this->input());
            return $this->ok($this->query()->show($context, $id));
        });
    }

    public function introductionPreview()
    {
        return $this->execute(fn($context) => $this->ok($this->ownerService()->previewIntroduction($context, $this->input())));
    }

    public function introductionPost()
    {
        return $this->execute(function ($context) {
            $id = $this->ownerService()->postIntroduction($context, $this->input());
            return $this->ok($this->query()->show($context, $id));
        });
    }

    public function reversalPreview(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->correctionService()->previewReverse($context, $id, $this->input())));
    }

    public function reverse(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            $result = $this->correctionService()->reverse($context, $id, $this->input());
            return $this->ok($this->query()->correctionState($context, $id), 200, ['saved' => $result]);
        });
    }

    public function correctionPreview(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->correctionService()->previewCorrection($context, $id, $this->input())));
    }

    public function correct(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            $result = $this->correctionService()->correct($context, $id, $this->input());
            return $this->ok($this->query()->correctionState($context, $id), 200, ['saved' => $result]);
        });
    }
}
