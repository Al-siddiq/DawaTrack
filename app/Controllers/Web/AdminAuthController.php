<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use Config\Services;

final class AdminAuthController extends BaseController
{
    public function login()
    {
        $user = auth()->user();
        if ($user !== null && $user->inGroup('platform_admin')) {
            return redirect()->to(site_url('platform-admin'));
        }
        if ($user !== null) {
            return $this->response
                ->setStatusCode(403)
                ->setBody(view('auth/admin_denied', ['pageTitle' => 'Administrator access denied', 'brand' => null]));
        }

        session()->setTempdata('beforeLoginUrl', '/platform-admin', 600);
        session()->set('authentication_area', 'platform_admin');

        return redirect()->to(site_url('login'));
    }

    public function stepUp(): string
    {
        return view('auth/admin_step_up', [
            'pageTitle' => 'Administrator verification',
            'brand' => null,
        ]);
    }

    public function restart()
    {
        $destination = (string) (session()->getTempdata('platform_mfa_destination') ?? '/platform-admin');
        if (($destination !== '/platform-admin' && ! str_starts_with($destination, '/platform-admin/'))
            || str_starts_with($destination, '//')
        ) {
            $destination = '/platform-admin';
        }
        $user = auth()->user();
        if ($user !== null) {
            Services::platformMfa()->clear((int) $user->id, 'Recent MFA expired; a new Shield authentication challenge was requested.');
        }

        auth()->logout();
        session()->regenerate(true);
        session()->setTempdata('beforeLoginUrl', $destination, 600);
        session()->set('authentication_area', 'platform_admin');

        return redirect()->to(site_url('login'));
    }
}
