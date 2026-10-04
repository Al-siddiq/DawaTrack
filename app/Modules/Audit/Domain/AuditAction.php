<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use App\Exceptions\ApiException;

final class AuditAction
{
    private const CODES = [
        'admin.mfa_cleared',
        'admin.mfa_verified',
        'branding.asset_replaced',
        'branding.updated',
        'domain.activated',
        'domain.challenge_rotated',
        'domain.deactivated',
        'domain.platform_fallback',
        'domain.primary_switched',
        'domain.requested',
        'domain.tls_failed',
        'domain.tls_ready',
        'domain.verified',
        'identity.password_changed',
        'owner.created',
        'permission.granted',
        'permission.revoked',
        'shop.deactivate',
        'shop.profile_updated',
        'shop.reactivate',
        'staff.activated',
        'staff.created',
        'staff.credentials_reset',
        'staff.deactivated',
        'subscription.activate',
        'subscription.downgrade',
        'subscription.expired_recorded',
        'subscription.extend',
        'subscription.renew',
        'subscription.upgrade',
        'tenant.close',
        'tenant.onboarded',
        'tenant.reactivate',
        'tenant.suspend',
    ];

    public static function validate(string $code): string
    {
        $code = trim($code);
        if (! in_array($code, self::CODES, true)) {
            throw new ApiException('AUDIT_VOCABULARY_INVALID', 'Audit action code is not approved.', 422);
        }

        return $code;
    }
}
