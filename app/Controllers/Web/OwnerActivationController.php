<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use Config\Services;
use Throwable;

final class OwnerActivationController extends BaseController
{
    public function show()
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to(site_url('login'));
        }

        $pending = $this->pendingMembership((int) $user->id);
        if ($pending === null) {
            return redirect()->to(site_url('/'));
        }

        $identity = db_connect()->table('auth_identities')
            ->where(['user_id' => (int) $user->id, 'type' => 'email_password'])
            ->get()->getRowArray();
        $requiresPasswordReset = $identity !== null && (int) ($identity['force_reset'] ?? 0) === 1;
        $magicLinkSession = $this->isMagicLinkSession();

        if ($requiresPasswordReset && ! $magicLinkSession) {
            return redirect()->to(site_url('change-password'));
        }

        return view('auth/owner_activate', [
            'businessName' => $pending['business_name'],
            'ownerEmail' => $pending['owner_email'],
            'requiresPasswordReset' => $requiresPasswordReset,
            'magicLinkSession' => $magicLinkSession,
            'errors' => session()->getFlashdata('errors') ?? [],
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function complete()
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to(site_url('login'));
        }

        $pending = $this->pendingMembership((int) $user->id);
        if ($pending === null) {
            return redirect()->to(site_url('/'));
        }

        $identity = db_connect()->table('auth_identities')
            ->where(['user_id' => (int) $user->id, 'type' => 'email_password'])
            ->get()->getRowArray();
        $requiresPasswordReset = $identity !== null && (int) ($identity['force_reset'] ?? 0) === 1;
        $magicLinkSession = $this->isMagicLinkSession();

        $newPassword = null;
        if ($requiresPasswordReset) {
            if (! $magicLinkSession) {
                return redirect()->to(site_url('change-password'));
            }
            $rules = [
                'password' => ['label' => 'New password', 'rules' => ['required', 'strong_password']],
                'password_confirm' => ['label' => 'Confirm new password', 'rules' => ['required', 'matches[password]']],
            ];
            if (! $this->validateData($this->request->getPost(), $rules)) {
                return redirect()->to(site_url('owner/activate'))->with('errors', $this->validator->getErrors());
            }
            $newPassword = (string) $this->request->getPost('password');
        }

        try {
            $result = Services::onboardingService()->completeOwnerActivation((int) $user->id, $newPassword);
            if ($result['password_changed']) {
                service('userSessionRevocation')->revokeAll((int) $user->id);
                service('userSessionRevocation')->clearSessionMarker();
                service('userSessionRevocation')->markCurrentSession();
            }
            session()->removeTempdata('magicLogin');
            return redirect()->to(site_url('/'))->with('message', 'Your Owner access is active. Welcome to DawaTrack.');
        } catch (ApiException $exception) {
            return redirect()->to(site_url('owner/activate'))->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'owner.activation_failed {request_id} {exception_class}', [
                'request_id' => Services::requestContext()->requestId(),
                'exception_class' => $exception::class,
            ]);
            return redirect()->to(site_url('owner/activate'))->with('error', 'Your Owner access could not be activated. Please try again.');
        }
    }

    /** @return array<string, mixed>|null */
    private function pendingMembership(int $userId): ?array
    {
        return db_connect()->query(<<<'SQL'
SELECT t.legal_or_business_name AS business_name,
       i.secret AS owner_email
FROM tenant_memberships m
INNER JOIN tenants t ON t.id = m.tenant_id
INNER JOIN auth_identities i ON i.user_id = m.user_id AND i.type = 'email_password'
WHERE m.user_id = ?
  AND m.role = 'PRIMARY_OWNER'
  AND m.status = 'INVITED'
  AND t.status = 'ONBOARDING'
LIMIT 1
SQL, [$userId])->getRowArray();
    }

    private function isMagicLinkSession(): bool
    {
        $marker = session()->getTempdata('magicLogin');
        return $marker === true || $marker === 1 || $marker === '1';
    }
}
