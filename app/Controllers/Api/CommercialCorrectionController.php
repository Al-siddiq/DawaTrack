<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Modules\Sales\Application\{
    CommercialReversalService,
    CommercialEvents,
    CommercialEvidence,
};
use App\Modules\Sales\Infrastructure\{
    CommercialCorrectionQuery,
    ReturnQuery,
    SaleDraftQuery,
    SaleQuery,
};
use Config\Services;
final class CommercialCorrectionController extends CommercialController
{
    private function service(): CommercialReversalService
    {
        $db = db_connect();
        return new CommercialReversalService(
            $db,
            $this->access(),
            new CommercialEvents(
                $db,
                Services::auditService(),
                Services::changeSequenceService(),
            ),
            Services::requestContext(),
        );
    }
    private function query(): CommercialCorrectionQuery
    {
        return new CommercialCorrectionQuery(db_connect(), $this->access());
    }
    public function preview(string $id)
    {
        return $this->execute(
            fn($c) => $this->ok(
                $this->service()->preview($c, $id, $this->input()),
            ),
        );
    }
    public function reverse(string $id)
    {
        return $this->execute(function ($c) use ($id) {
            $created = $this->service()->reverse($c, $id, $this->input());
            return $this->ok($this->query()->detail($c, $created));
        });
    }
    public function replacement(string $id)
    {
        return $this->execute(function ($c) use ($id) {
            $created = $this->service()->replacement($c, $id, $this->input());
            $r = (new CommercialEvidence(db_connect()))->header($c, $created);
            $draft =
                $r["kind"] === "RETURN"
                    ? (new ReturnQuery(db_connect(), $this->access()))->detail(
                        $c,
                        $created,
                    )
                    : ($r["status"] === "DRAFT"
                        ? (new SaleDraftQuery(
                            db_connect(),
                            $this->access(),
                        ))->detail($c, $created)
                        : (new SaleQuery(db_connect(), $this->access()))->state(
                            $c,
                            $created,
                        ));
            return $this->ok(
                [
                    "public_id" => $created,
                    "kind" => $r["kind"],
                    "status" => $r["status"],
                    "draft" => $draft,
                ],
                201,
            );
        });
    }
    public function state(string $id)
    {
        return $this->execute(
            fn($c) => $this->ok($this->query()->state($c, $id)),
        );
    }
    public function show(string $id)
    {
        return $this->execute(
            fn($c) => $this->ok($this->query()->detail($c, $id)),
        );
    }
}
