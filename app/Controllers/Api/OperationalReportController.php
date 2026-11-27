<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\CustomerDebt\Infrastructure\CustomerDebtReportQuery;
use App\Modules\Inventory\Infrastructure\StockReportQuery;
use App\Modules\Reconciliation\Infrastructure\ReconciliationReportQuery;

final class OperationalReportController extends CommercialController
{
    private function stockReports(): StockReportQuery
    {
        return new StockReportQuery(db_connect(), $this->access());
    }

    private function customerDebtReports(): CustomerDebtReportQuery
    {
        return new CustomerDebtReportQuery(db_connect(), $this->access());
    }

    private function reconciliationReports(): ReconciliationReportQuery
    {
        return new ReconciliationReportQuery(db_connect(), $this->access());
    }

    public function stock()
    {
        return $this->execute(fn ($context) => $this->ok(
            $this->stockReports()->report($context, $this->request->getGet()),
        ));
    }

    public function customerDebt()
    {
        return $this->execute(fn ($context) => $this->ok(
            $this->customerDebtReports()->report($context, $this->request->getGet()),
        ));
    }

    public function reconciliation()
    {
        return $this->execute(fn ($context) => $this->ok(
            $this->reconciliationReports()->report($context, $this->request->getGet()),
        ));
    }
}
