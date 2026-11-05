<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Modules\Sales\Application\{ReturnService, CommercialEvents};
use App\Modules\Sales\Infrastructure\ReturnQuery;
use Config\Services;
final class ReturnController extends CommercialController
{
    private function events(): CommercialEvents
    {
        return new CommercialEvents(
            db_connect(),
            Services::auditService(),
            Services::changeSequenceService(),
        );
    }
    private function returns(): ReturnService
    {
        return new ReturnService(
            db_connect(),
            $this->access(),
            $this->events(),
            Services::requestContext(),
        );
    }
    private function query(): ReturnQuery
    {
        return new ReturnQuery(db_connect(), $this->access());
    }
    public function create(string $id)
    {
        return $this->execute(function ($c) use ($id) {
            $created = $this->returns()->create($c, $id, $this->input());
            return $this->ok($this->query()->detail($c, $created), 201);
        });
    }
    public function update(string $id)
    {
        return $this->execute(function ($c) use ($id) {
            $this->returns()->update($c, $id, $this->input());
            return $this->ok($this->query()->detail($c, $id));
        });
    }
    public function estimate(string $id)
    {
        return $this->execute(
            fn($c) => $this->ok($this->returns()->estimate($c, $id)),
        );
    }
    public function preview(string $id)
    {
        return $this->execute(
            fn($c) => $this->ok(
                $this->returns()->preview($c, $id, $this->input()),
            ),
        );
    }
    public function post(string $id)
    {
        return $this->execute(function ($c) use ($id) {
            $this->returns()->post($c, $id, $this->input());
            return $this->ok($this->query()->detail($c, $id));
        });
    }
    public function discard(string $id)
    {
        return $this->execute(function ($c) use ($id) {
            $this->returns()->discard($c, $id, $this->input());
            return $this->ok();
        });
    }
    public function detail(string $id)
    {
        return $this->execute(
            fn($c) => $this->ok($this->query()->detail($c, $id)),
        );
    }
    public function index()
    {
        return $this->execute(
            fn($c) => $this->ok(
                $this->query()->page($c, $this->request->getGet()),
            ),
        );
    }
    public function eligible(string $id)
    {
        return $this->execute(
            fn($c) => $this->ok($this->query()->eligible($c, $id)),
        );
    }
}
