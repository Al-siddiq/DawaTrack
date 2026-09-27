<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\Http\ApiResponse;
use Config\Services;
use CodeIgniter\Shield\Entities\User;
use DomainException;
use Throwable;

final class OnboardingController extends BaseController
{
    public function create()
    {
        $input = $this->request->getJSON(true) ?? [];
        try {
            $email = strtolower(trim((string) ($input['owner_email'] ?? '')));
            $password = (string) ($input['owner_password'] ?? '');
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
                throw new DomainException('Enter a valid Owner email and a password of at least 12 characters.');
            }
            $provider = auth()->getProvider();
            $provider->save(new User(['username' => trim((string) ($input['owner_name'] ?? '')), 'email' => $email, 'password' => $password, 'active' => 1]));
            $input['owner_user_id'] = (int) $provider->getInsertID();
            $result = Services::onboardingService()->onboard($input, (int) auth()->id());
            $result['owner_email'] = $email;
            return ApiResponse::success($this->response, $result, Services::requestContext()->requestId(), 201);
        } catch (DomainException $exception) {
            return ApiResponse::error($this->response, 'VALIDATION_FAILED', $exception->getMessage(), Services::requestContext()->requestId(), 422);
        } catch (Throwable $exception) {
            log_message('error', 'tenant.onboarding_failed {request_id} {exception}', ['request_id'=>Services::requestContext()->requestId(),'exception'=>$exception]);
            return ApiResponse::error($this->response, 'ONBOARDING_FAILED', 'The Tenant could not be created.', Services::requestContext()->requestId(), 500);
        }
    }
}
