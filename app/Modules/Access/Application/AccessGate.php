<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use CodeIgniter\Database\BaseConnection;

final class AccessGate
{
    public function __construct(private BaseConnection $database)
    {
    }

    public function evaluate(int $tenantId, int $shopId): AccessDecision
    {
        $tenant = $this->database->table('tenants')->select('status')->where('id', $tenantId)->get()->getRowArray();
        $shop = $this->database->table('shops')->select('status')->where(['id' => $shopId, 'tenant_id' => $tenantId])->get()->getRowArray();
        if (! $tenant || ! $shop || $tenant['status'] === 'CLOSED') {
            return new AccessDecision('CLOSED', 'This business account is closed.');
        }
        if ($tenant['status'] === 'SUSPENDED' || $shop['status'] !== 'ACTIVE') {
            return new AccessDecision('BLOCKED', 'This shop is temporarily unavailable.');
        }
        if ($tenant['status'] !== 'ACTIVE') {
            return new AccessDecision('BLOCKED', 'Shop onboarding is not complete.');
        }

        $now = gmdate('Y-m-d H:i:s.u');
        $subscription = $this->database->table('subscriptions')->where('tenant_id', $tenantId)->where('starts_at <=', $now)->where('ends_at >', $now)->orderBy('starts_at', 'DESC')->get(1)->getRowArray();
        if (! $subscription) {
            return new AccessDecision('READ_ONLY', 'Your plan has ended. You can view records but cannot make changes.');
        }

        return new AccessDecision('FULL', 'Access is active.', $subscription['plan_code_snapshot'], $subscription['ends_at']);
    }
}
