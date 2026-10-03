<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

final class PasswordChangeController extends BaseController
{
    public function show(): string|RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return redirect()->to(service('sameOriginUrl')->to('login'));
        }

        if (! $user->requiresPasswordReset()) {
            return redirect()->to(service('sameOriginUrl')->to('/'));
        }

        return view('auth/change_password');
    }

    public function update(): RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return redirect()->to(service('sameOriginUrl')->to('login'));
        }

        if (! $user->requiresPasswordReset()) {
            return redirect()->to(service('sameOriginUrl')->to('/'));
        }

        $rules = [
            'current_password' => [
                'label' => 'Current password',
                'rules' => [
                    'required',
                ],
            ],

            'password' => [
                'label' => 'New password',
                'rules' => [
                    'required',
                    'strong_password',
                ],
            ],

            'password_confirm' => [
                'label' => 'Confirm new password',
                'rules' => [
                    'required',
                    'matches[password]',
                ],
            ],
        ];

        if (! $this->validateData(
            $this->request->getPost(),
            $rules
        )) {
            /*
             * Do not use withInput() here:
             * password values must never be repopulated.
             */
            return redirect()
                ->to(service('sameOriginUrl')->to('change-password'))
                ->with(
                    'errors',
                    $this->validator->getErrors()
                );
        }

        $currentPassword = (string) $this->request->getPost(
            'current_password'
        );

        $newPassword = (string) $this->request->getPost(
            'password'
        );

        /*
         * Re-authenticate before allowing the credential mutation.
         */
        $result = auth()->check([
            'email'    => $user->email,
            'password' => $currentPassword,
        ]);

        if (! $result->isOK()) {
            return redirect()
                ->to(service('sameOriginUrl')->to('change-password'))
                ->with(
                    'error',
                    'The current password is incorrect.'
                );
        }

        $database = db_connect();

        $database
            ->transException(true)
            ->transBegin();

        try {
            $users = auth()->getProvider();

            $user->fill([
                'password' => $newPassword,
            ]);

            $users->save($user);

            /*
             * Clear Shield's force_reset flag only after
             * the new password has been persisted.
             */
            $user->undoForcePasswordReset();

            /*
             * Record security evidence without password data.
             */
            service('auditService')->record([
                'actor_type' => 'USER',
                'actor_user_id' => (int) $user->id,
                'action_code' => 'identity.password_changed',
                'category' => 'SECURITY',
                'resource_type' => 'user_identity',
                'reason' => 'Forced password change completed.',
                'change_metadata' => [
                    'forced_reset' => true,
                ],
            ]);

            if (! $database->transCommit()) {
                throw new \RuntimeException(
                    'Password change could not be committed.'
                );
            }
        } catch (\Throwable $exception) {
            $database->transRollback();

            log_message(
                'error',
                'security.password_change_failed {user_id} {exception_class}',
                [
                    'user_id' => (int) $user->id,
                    'exception_class' => $exception::class,
                ]
            );

            return redirect()
                ->to(service('sameOriginUrl')->to('change-password'))
                ->with(
                    'error',
                    'The password could not be changed. Please try again.'
                );
        }

        /*
         * Existing sessions should not survive a credential change.
         */
        service('userSessionRevocation')
            ->revokeAll((int) $user->id);

        service('userSessionRevocation')
            ->clearSessionMarker();

        if ($user->inGroup('platform_admin')) {
            service('platformMfa')->clear(
                (int) $user->id,
                'Platform Administrator password changed.'
            );
        }

        auth()->logout();

        return redirect()
            ->to(service('sameOriginUrl')->to('login'))
            ->with(
                'message',
                'Password changed successfully. Sign in with your new password.'
            );
    }
}