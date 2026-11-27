<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\Sales\Infrastructure\SalesReportQuery;

final class SalesReportController extends CommercialController
{
    private function reports(): SalesReportQuery
    {
        return new SalesReportQuery(db_connect(), $this->access());
    }

    public function sales()
    {
        return $this->execute(
            fn ($context) => $this->ok(
                $this->reports()->report($context, $this->request->getGet()),
            ),
        );
    }
}
