<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use App\Exceptions\ApiException;

final class AuditResource
{
    private const TYPES = [
        'platform_admin_authentication',
        'product',
        'reconciliation_session',
        'shop',
        'shop_brand_asset',
        'shop_branding_profile',
        'shop_domain',
        'subscription',
        'stock_position',
        'stock_intake',
        'stock_movement',
        'support_audit_scope',
        'tenant',
        'tenant_membership',
        'user_identity',
    ];

    public static function validate(string $type): string
    {
        $type = trim($type);
        if (! in_array($type, self::TYPES, true)) {
            throw new ApiException('AUDIT_VOCABULARY_INVALID', 'Audit resource type is not approved.', 422);
        }

        return $type;
    }
}
