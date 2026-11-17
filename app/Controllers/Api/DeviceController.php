<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\ApiException;
use App\Modules\Device\Application\DeviceService;
use App\Modules\Device\Infrastructure\DeviceQuery;
use Config\Services;

final class DeviceController extends CommercialController
{
    private function query(): DeviceQuery
    {
        return new DeviceQuery(db_connect());
    }

    private function deviceService(): DeviceService
    {
        return new DeviceService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService());
    }

    private function cookieName(): string
    {
        return ENVIRONMENT === 'production' ? '__Host-dt_device' : 'dt_device';
    }

    private function proof(): ?string
    {
        $value = $this->request->getCookie($this->cookieName());
        return is_string($value) ? $value : null;
    }

    private function result(array $result)
    {
        if (isset($result['cookie_value'])) {
            $this->response->setCookie([
                'name' => $this->cookieName(), 'value' => $result['cookie_value'], 'expire' => 31536000,
                'path' => '/', 'domain' => '', 'prefix' => '', 'secure' => ENVIRONMENT !== 'development' || $this->request->isSecure(),
                'httponly' => true, 'samesite' => 'Strict',
            ]);
            unset($result['cookie_value']);
        }
        return $this->ok($result);
    }

    public function index()
    {
        return $this->execute(function ($context) {
            $page = $this->request->getGet('page') ?? '1';
            $size = $this->request->getGet('per_page') ?? '20';
            if (!is_scalar($page) || !is_scalar($size) || !preg_match('/^[1-9][0-9]{0,5}$/D', (string) $page)
                || !preg_match('/^[1-9][0-9]?$/D', (string) $size) || (int) $size > 50) {
                throw new ApiException('VALIDATION_FAILED', 'Choose a valid Device page and page size of at most 50.', 422);
            }
            $data = $this->query()->history($context, (int) $page, (int) $size);
            return $this->ok(['devices' => $data['devices']], meta: ['pagination' => $data['pagination']]);
        });
    }

    public function current()
    {
        return $this->execute(function ($context) {
            $query = $this->query();
            $query->requireRead($context);
            $row = $query->authenticated($context, $this->proof());
            return $this->ok(['device' => $row ? DeviceQuery::safe($row) : null]);
        });
    }

    public function register()
    {
        return $this->execute(fn($context) => $this->result($this->deviceService()->register($context, $this->input(), $this->proof())));
    }

    public function validateCurrent()
    {
        return $this->execute(function ($context) {
            \App\Modules\Device\Application\DeviceInput::only($this->input(), []);
            return $this->result($this->deviceService()->validateCurrent($context, $this->proof()));
        });
    }

    public function rename(string $id)
    {
        return $this->execute(fn($context) => $this->result($this->deviceService()->rename($context, $id, $this->input())));
    }

    public function revoke(string $id)
    {
        return $this->execute(fn($context) => $this->result($this->deviceService()->revoke($context, $id, $this->input())));
    }

    public function recover(string $id)
    {
        return $this->execute(fn($context) => $this->result($this->deviceService()->recover($context, $id, $this->input())));
    }
}
