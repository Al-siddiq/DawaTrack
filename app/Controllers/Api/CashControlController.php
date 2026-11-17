<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Modules\Reconciliation\Application\{CashControlInput, CashControlService, ReadinessProof};
use App\Modules\Reconciliation\Infrastructure\CashControlQuery;
use Config\Services;

final class CashControlController extends CommercialController
{
    private function query(): CashControlQuery
    {
        return new CashControlQuery(db_connect(), $this->access());
    }

    private function receipt(): ReadinessProof
    {
        return new ReadinessProof((string) config('Encryption')->key);
    }

    private function service(): CashControlService
    {
        return new CashControlService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService(), (string) config('Encryption')->key);
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

    private function collection(array $data)
    {
        $pagination = $data['pagination'];
        unset($data['pagination']);
        return $this->ok($data, meta: ['pagination' => $pagination]);
    }

    public function index()
    {
        return $this->execute(fn($context) => $this->collection($this->query()->history($context, ...$this->paging())));
    }

    public function members()
    {
        return $this->execute(function ($context) {
            $search = $this->request->getGet('search') ?? '';
            if (!is_string($search)) {
                throw new \App\Exceptions\ApiException('VALIDATION_FAILED', 'Use text to search members.', 422);
            }
            return $this->collection($this->query()->members($context, $search, ...$this->paging()));
        });
    }

    public function show(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query()->detail($context, CashControlInput::uuid($id), $this->cookie())));
    }

    public function start()
    {
        return $this->execute(function ($context) {
            $id = $this->service()->start($context, $this->input());
            return $this->ok($this->query()->detail($context, $id, $this->cookie()));
        });
    }

    public function readiness(string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query()->readiness($context, CashControlInput::uuid($id), ...$this->paging())));
    }

    public function challenge(string $id)
    {
        return $this->execute(fn($context) => $this->ok($this->query()->challenge($context, CashControlInput::uuid($id), $this->cookie(), $this->receipt())));
    }

    public function staff(string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query()->staff($context, CashControlInput::uuid($id), ...$this->paging())));
    }

    public function notes(string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query()->notes($context, CashControlInput::uuid($id), ...$this->paging())));
    }

    public function sourceStatus(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            $kind = $this->request->getGet('kind');
            if (!is_string($kind)) {
                throw new \App\Exceptions\ApiException('VALIDATION_FAILED', 'Choose a saved record kind.', 422);
            }
            return $this->ok($this->query()->sourceStatus($context, CashControlInput::uuid($id), $kind,
                CashControlInput::uuid($this->request->getGet('public_id'))));
        });
    }

    public function lateOperations(string $id)
    {
        return $this->execute(fn($context) => $this->collection($this->query()->lateOperations($context, CashControlInput::uuid($id), ...$this->paging())));
    }

    private function perform(string $id, string $action)
    {
        return $this->execute(function ($context) use ($id, $action) {
            $id = CashControlInput::uuid($id);
            $input = $this->input();
            $service = $this->service();
            if (in_array($action, ['review', 'finalize'], true)) {
                CashControlInput::only($input, []);
                $service->$action($context, $id);
            } elseif ($action === 'acknowledge') {
                $service->acknowledge($context, $id, $input, $this->cookie());
            } else {
                $service->$action($context, $id, $input);
            }
            return $this->ok($this->query()->detail($context, $id, $this->cookie()));
        });
    }

    public function count(string $id)
    {
        return $this->perform($id, 'count');
    }
    public function commit(string $id)
    {
        return $this->perform($id, 'commit');
    }
    public function review(string $id)
    {
        return $this->perform($id, 'review');
    }
    public function explain(string $id)
    {
        return $this->perform($id, 'explain');
    }
    public function finalize(string $id)
    {
        return $this->perform($id, 'finalize');
    }
    public function abort(string $id)
    {
        return $this->perform($id, 'abort');
    }
    public function acknowledge(string $id)
    {
        return $this->perform($id, 'acknowledge');
    }
    public function exclude(string $id)
    {
        return $this->perform($id, 'exclude');
    }
    public function note(string $id)
    {
        return $this->perform($id, 'note');
    }
}
