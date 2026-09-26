<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Audit\Application\AuditService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DomainException;

final class StaffService
{
    public function __construct(private BaseConnection $database, private AuditService $audit)
    {
    }

    public function listing(ShopContext $context): array
    {
        $context->requireOwner();
        return $this->database->table('tenant_memberships m')->select('m.id,m.public_id,m.user_id,m.role,m.status,m.activated_at,m.deactivated_at,u.username')
            ->join('users u', 'u.id=m.user_id')->where('m.tenant_id', $context->tenantId)->orderBy('m.created_at')->get()->getResultArray();
    }

    public function create(ShopContext $context, array $input): array
    {
        $context->requireOwner();
        $this->assertCapacity($context->tenantId);
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new DomainException('Enter a valid email and a password of at least 12 characters.');
        }
        $provider = auth()->getProvider();
        $user = new User(['username' => trim((string) ($input['name'] ?? '')), 'email' => $email, 'password' => $password, 'active' => 1]);
        $now = gmdate('Y-m-d H:i:s.u');
        $publicId = Uuid::v4();
        $this->database->transException(true)->transStart();
        $provider->save($user);
        $userId = (int) $provider->getInsertID();
        $this->database->table('tenant_memberships')->insert(['public_id'=>$publicId,'tenant_id'=>$context->tenantId,'user_id'=>$userId,'role'=>'STAFF','status'=>'ACTIVE','activated_at'=>$now,'created_by_user_id'=>$context->userId,'created_at'=>$now,'updated_at'=>$now]);
        $this->audit->record(['tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'actor_type'=>'USER','actor_user_id'=>$context->userId,'actor_membership_id'=>$context->membershipId,'action_code'=>'staff.created','category'=>'AUTHORIZATION','resource_type'=>'membership','resource_public_id'=>$publicId]);
        $this->database->transComplete();
        return ['public_id'=>$publicId,'email'=>$email,'status'=>'ACTIVE'];
    }

    public function setActive(ShopContext $context, int $membershipId, bool $active, string $reason): void
    {
        $context->requireOwner();
        if (trim($reason) === '') throw new DomainException('A reason is required.');
        if ($active) $this->assertCapacity($context->tenantId);
        $membership = $this->database->table('tenant_memberships')->where(['id'=>$membershipId,'tenant_id'=>$context->tenantId,'role'=>'STAFF'])->get()->getRowArray();
        if (! $membership) throw new DomainException('Staff membership was not found.');
        $now = gmdate('Y-m-d H:i:s.u');
        $status = $active ? 'ACTIVE' : 'DEACTIVATED';
        $this->database->transException(true)->transStart();
        $this->database->table('tenant_memberships')->where('id',$membershipId)->update(['status'=>$status,'activated_at'=>$active?$now:$membership['activated_at'],'deactivated_at'=>$active?null:$now,'updated_at'=>$now]);
        $this->database->table('users')->where('id',$membership['user_id'])->update(['active'=>$active?1:0]);
        $this->audit->record(['tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'actor_type'=>'USER','actor_user_id'=>$context->userId,'actor_membership_id'=>$context->membershipId,'action_code'=>$active?'staff.activated':'staff.deactivated','category'=>'AUTHORIZATION','resource_type'=>'membership','resource_public_id'=>$membership['public_id'],'reason'=>trim($reason)]);
        $this->database->transComplete();
    }

    private function assertCapacity(int $tenantId): void
    {
        $now = gmdate('Y-m-d H:i:s.u');
        $subscription = $this->database->table('subscriptions')->where('tenant_id',$tenantId)->where('starts_at <=',$now)->where('ends_at >',$now)->orderBy('starts_at','DESC')->get(1)->getRowArray();
        if (! $subscription) throw new DomainException('An active entitlement is required to activate Staff.');
        $active = $this->database->table('tenant_memberships')->where(['tenant_id'=>$tenantId,'role'=>'STAFF','status'=>'ACTIVE'])->countAllResults();
        if ($active >= (int) $subscription['max_active_staff_snapshot']) throw new DomainException('Your current plan Staff allowance has been reached.');
    }
}
