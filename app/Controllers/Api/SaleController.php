<?php

declare(strict_types=1);
namespace App\Controllers\Api;
use App\Modules\Sales\Application\SalePostingService;
use App\Modules\Sales\Infrastructure\SaleQuery;
use Config\Services;

final class SaleController extends CommercialController
{
    private function query(): SaleQuery
    {
        return new SaleQuery(db_connect(), $this->access());
    }
    public function index()
    {
        return $this->execute(function ($context) {
            $page = $this->query()->page($context, $this->request->getGet());
            return $this->ok($page["items"], 200, [
                "pagination" => $page["pagination"],
            ]);
        });
    }
    public function show(string $id)
    {
        return $this->execute(
            fn($context) => $this->ok($this->query()->state($context, $id)),
        );
    }
    public function post(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            (new SalePostingService(
                db_connect(),
                $this->access(),
                Services::auditService(),
                Services::changeSequenceService(),
                Services::requestContext(),
            ))->post($context, $id, $this->input());
            return $this->ok($this->query()->state($context, $id));
        });
    }
    public function receivable(string $id)
    {
        return $this->execute(
            fn($context) => $this->ok(
                $this->query()->receivable($context, $id),
            ),
        );
    }
}
