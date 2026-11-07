<?php

declare(strict_types=1);

namespace App\Authentication\Actions;

use CodeIgniter\Shield\Authentication\Actions\ConditionalActionInterface;
use CodeIgniter\Shield\Authentication\Actions\Email2FA;
use CodeIgniter\Shield\Entities\User;

final class PlatformAdminEmail2FA extends Email2FA implements ConditionalActionInterface
{
    /**
     * DawaTrack requires Shield Email2FA only for Platform Administrators.
     */
    public function verify(\CodeIgniter\HTTP\IncomingRequest $request)
    {
        $authenticator = auth('session')->getAuthenticator();
        $pending = $authenticator->getPendingUser();
        $response = parent::verify($request);
        // Shield returns a redirect only after checkAction completes this challenge.
        if ($pending !== null && $response instanceof \CodeIgniter\HTTP\RedirectResponse
            && auth()->loggedIn() && (int) auth()->id() === (int) $pending->id
            && $pending->inGroup('platform_admin')) {
            service('platformMfa')->establish((int) $pending->id);
        }
        return $response;
    }

    public function appliesTo(User $user): bool
    {
        return $user->inGroup('platform_admin');
    }
}