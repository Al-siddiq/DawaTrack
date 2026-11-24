<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\Sales\Infrastructure\SalesReportQuery;

final class SalesReportController extends CommercialController
{
    public function index()
    {
        return $this->execute(fn ($context) => $this->ok(
            (new SalesReportQuery(db_connect(), $this->access()))->show($context, $this->request->getGet()),
        ));
    }
}
