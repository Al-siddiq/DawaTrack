<?php

declare(strict_types=1);
namespace App\Controllers\Api;
use App\Modules\Sales\Application\SaleDraftService;
use App\Modules\Sales\Infrastructure\SaleDraftQuery;
use Config\Services;

final class SaleDraftController extends CommercialController
{
    private function query(): SaleDraftQuery
    {
        return new SaleDraftQuery(db_connect(), $this->access());
    }
    private function service(): SaleDraftService
    {
        return new SaleDraftService(
            db_connect(),
            $this->access(),
            Services::auditService(),
        );
    }
    public function index()
    {
        return $this->execute(function ($context) {
            $result = $this->query()->page($context, $this->request->getGet());
            return $this->ok($result["items"], 200, [
                "pagination" => $result["pagination"],
            ]);
        });
    }
    public function show(string $id)
    {
        return $this->execute(
            fn($context) => $this->ok($this->query()->detail($context, $id)),
        );
    }
    public function products()
    {
        return $this->execute(
            fn($context) => $this->ok(
                $this->query()->lookup(
                    $context,
                    "products",
                    $this->request->getGet(),
                ),
            ),
        );
    }
    public function customers()
    {
        return $this->execute(
            fn($context) => $this->ok(
                $this->query()->lookup(
                    $context,
                    "customers",
                    $this->request->getGet(),
                ),
            ),
        );
    }
    public function create()
    {
        return $this->execute(function ($context) {
            $id = $this->service()->create($context, $this->input());
            return $this->ok($this->query()->detail($context, $id), 201);
        });
    }
    public function update(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            $this->service()->update($context, $id, $this->input());
            return $this->ok($this->query()->detail($context, $id));
        });
    }
    public function addItem(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            $this->service()->addItem($context, $id, $this->input());
            return $this->ok($this->query()->detail($context, $id), 201);
        });
    }
    public function updateItem(string $id, string $item)
    {
        return $this->execute(function ($context) use ($id, $item) {
            $this->service()->updateItem($context, $id, $item, $this->input());
            return $this->ok($this->query()->detail($context, $id));
        });
    }
    public function removeItem(string $id, string $item)
    {
        return $this->execute(function ($context) use ($id, $item) {
            $this->service()->removeItem($context, $id, $item, $this->input());
            return $this->ok($this->query()->detail($context, $id));
        });
    }
    public function discard(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            $this->service()->discard($context, $id, $this->input());
            return $this->ok();
        });
    }
    public function preview(string $id)
    {
        return $this->execute(
            fn($context) => $this->ok(
                $this->service()->preview($context, $id, $this->input()),
            ),
        );
    }
}
