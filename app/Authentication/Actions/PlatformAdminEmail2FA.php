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
    public function appliesTo(User $user): bool
    {
        return $user->inGroup('platform_admin');
    }
}