<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use Config\Services;
use Throwable;

final class OnboardingController extends BaseController
{
    public function create()
    {
        try {
            $input = $this->request->getJSON(true) ?? [];
            $result = Services::onboardingService()->onboard($input, (int) auth()->id());
            $membershipPublicId = $result['owner_membership_public_id'];
            unset($result['owner_user_id'], $result['owner_membership_public_id']);

            try {
                $invitation = service('ownerInvitationService')->issueForMembership(
                    $membershipPublicId,
                    (int) auth()->id(),
                    (string) ($input['reason'] ?? ''),
                );
                $result['invitation_delivery_status'] = $invitation['delivery_status'];
                $result['invitation_expires_at'] = $invitation['expires_at'];
            } catch (Throwable $deliveryFailure) {
                log_message('error', 'tenant.onboarding_invitation_failed {request_id} {exception_class}', [
                    'request_id' => Services::requestContext()->requestId(),
                    'exception_class' => $deliveryFailure::class,
                ]);
                $result['invitation_delivery_status'] = 'FAILED';
            }

            return ApiResponse::success($this->response, $result, Services::requestContext()->requestId(), 201);
        } catch (ApiException $exception) {
            return ApiResponse::error(
                $this->response,
                $exception->errorCode,
                $exception->getMessage(),
                Services::requestContext()->requestId(),
                $exception->status,
                $exception->details,
            );
        } catch (Throwable $exception) {
            log_message('error', 'tenant.onboarding_failed {request_id} {exception_class}', [
                'request_id' => Services::requestContext()->requestId(),
                'exception_class' => $exception::class,
            ]);

            return ApiResponse::error($this->response, 'ONBOARDING_FAILED', 'The Tenant could not be created.', Services::requestContext()->requestId(), 500);
        }
    }
}
