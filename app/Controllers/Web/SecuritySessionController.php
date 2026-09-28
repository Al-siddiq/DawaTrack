<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use Config\Services;

final class SecuritySessionController extends BaseController
{
    public function logout()
    {
        $user = auth()->user();
        if ($user !== null && $user->inGroup('platform_admin')) {
            Services::platformMfa()->clear((int) $user->id, 'Platform Administrator signed out.');
        }

        auth()->logout();
        session()->destroy();

        return redirect()->to(site_url('auth?logged_out=1'));
    }
}
