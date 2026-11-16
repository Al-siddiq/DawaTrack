<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\ApiException;
use App\Modules\Inventory\Application\ReconciliationAdjustmentService;
use App\Modules\Reconciliation\Application\{CashControlInput, ControlService, ReadinessProof};
use App\Modules\Reconciliation\Infrastructure\ControlQuery;
use Config\Services;

final class ScopeControlController extends CommercialController
{
    private function scope(string $scope): string
    {
        if (!in_array($scope, ['STOCK', 'FULL'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose an approved server control route.', 422);
        }
        return $scope;
    }

    private function query(string $scope): ControlQuery
    {
        return new ControlQuery(db_connect(), $this->access(), $this->scope($scope));
    }

    private function service(string $scope): ControlService
    {
        return new ControlService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService(),
            (string) config('Encryption')->key, $this->scope($scope));
    }

    private function adjustment(string $scope): ReconciliationAdjustmentService
    {
        return new ReconciliationAdjustmentService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService(),
            Services::requestContext(), $this->scope($scope));
    }

    private function cookie(): ?string
    {
        $value = $this->request->getCookie(ENVIRONMENT === 'production' ? '__Host-dt_device' : 'dt_device');
        return is_string($value) ? $value : null;
    }

    private function paging(): array
    {
        return [CashControlInput::page($this->request->getGet('page') ?? '1'), CashControlInput::page($this->request->getGet('per_page') ?? '20', 50)];
    }

    private function search(): string
    {
        $value = $this->request->getGet('search') ?? '';
        if (!is_string($value) || mb_strlen($value) > 100) {
            throw new ApiException('VALIDATION_FAILED', 'Use a bounded text search.', 422);
        }
        return $value;
    }

    private function collection(array $data)
    {
        $page = $data['pagination'];
        unset($data['pagination']);
        return $this->ok($data, meta: ['pagination' => $page]);
    }

    public function index(string $scope)
    {
        return $this->execute(fn($context) => $this->collection($this->query($scope)->history($context, ...$this->paging())));
    }

    public function members(string $scope)
    {
        return $this->execute(fn($context) => $this->collection($this->query($scope)->members($context, $this->search(), ...$this->paging())));
    }

    public function show(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query($scope)->detail($context, CashControlInput::uuid($id), $this->cookie())));
    }

    public function start(string $scope)
    {
        return $this->execute(function ($context) use ($scope) {
            $id = $this->service($scope)->start($context, $this->input());
            return $this->ok($this->query($scope)->detail($context, $id, $this->cookie()));
        });
    }

    public function readiness(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query($scope)->readiness($context, CashControlInput::uuid($id), ...$this->paging())));
    }

    public function challenge(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query($scope)->challenge($context, CashControlInput::uuid($id), $this->cookie(),
            new ReadinessProof((string) config('Encryption')->key))));
    }

    public function stockCounts(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query($scope)->stockCounts($context, CashControlInput::uuid($id), $this->search(), ...$this->paging())));
    }

    public function staff(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query($scope)->staff($context, CashControlInput::uuid($id), ...$this->paging())));
    }

    public function notes(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query($scope)->notes($context, CashControlInput::uuid($id), ...$this->paging())));
    }

    public function lateOperations(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query($scope)->lateOperations($context, CashControlInput::uuid($id), ...$this->paging())));
    }

    public function sourceStatus(string $scope, string $id)
    {
        return $this->execute(function ($context) use ($scope, $id) {
            $kind = $this->request->getGet('kind');
            if (!is_string($kind)) {
                throw new ApiException('VALIDATION_FAILED', 'Choose a saved record kind.', 422);
            }
            return $this->ok($this->query($scope)->sourceStatus($context, CashControlInput::uuid($id), $kind,
                CashControlInput::uuid($this->request->getGet('public_id'))));
        });
    }

    public function adjustmentPreview(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->adjustment($scope)->preview($context, CashControlInput::uuid($id), $this->input())));
    }

    public function adjust(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->adjustment($scope)->post($context, CashControlInput::uuid($id), $this->input())));
    }

    public function adjustmentStatus(string $scope, string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query($scope)->adjustmentStatus($context, CashControlInput::uuid($id),
            CashControlInput::uuid($this->request->getGet('public_id')))));
    }

    public function action(string $scope, string $id, string $action)
    {
        return $this->execute(function ($context) use ($scope, $id, $action) {
            $id = CashControlInput::uuid($id);
            $input = $this->input();
            $service = $this->service($scope);
            if (!in_array($action, ['count', 'commit', 'review', 'explain', 'finalize', 'abort', 'acknowledge', 'exclude', 'note'], true)) {
                throw new ApiException('VALIDATION_FAILED', 'Use an approved control command.', 422);
            }
            if (in_array($action, ['review', 'finalize'], true)) {
                CashControlInput::only($input, []);
                $service->$action($context, $id);
            } elseif ($action === 'acknowledge') {
                $service->acknowledge($context, $id, $input, $this->cookie());
            } else {
                $service->$action($context, $id, $input);
            }
            return $this->ok($this->query($scope)->detail($context, $id, $this->cookie()));
        });
    }
}
