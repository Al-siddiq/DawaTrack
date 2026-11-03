<?php

declare(strict_types=1);
namespace App\Controllers\Api;
use App\Modules\CustomerDebt\Application\CustomerService;
use App\Modules\CustomerDebt\Infrastructure\CustomerQuery;
use Config\Services;

final class CustomerController extends CommercialController
{
    private function query(): CustomerQuery
    {
        return new CustomerQuery(db_connect(), $this->access());
    }
    private function service(): CustomerService
    {
        return new CustomerService(
            db_connect(),
            $this->access(),
            Services::auditService(),
            Services::changeSequenceService(),
        );
    }
    public function index()
    {
        return $this->execute(function ($context) {
            $result = $this->query()->page($context, $this->request->getGet());
            return $this->ok($result["items"], 200, [
                "pagination" => $result["pagination"],
                "private_visible" => $result["private_visible"],
            ]);
        });
    }
    public function show(string $id)
    {
        return $this->execute(
            fn($context) => $this->ok($this->query()->detail($context, $id)),
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
    public function deactivate(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            $this->service()->deactivate($context, $id, $this->input());
            return $this->ok($this->query()->detail($context, $id));
        });
    }
}
