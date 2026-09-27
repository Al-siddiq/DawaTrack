<?php

declare(strict_types=1);

namespace App\Modules\BrandingDomain\Application;

use App\Modules\Audit\Application\AuditService;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use DomainException;

final class DomainService
{
    public function __construct(private BaseConnection $database, private AuditService $audit)
    {
    }

    public function requestCustom(int $tenantId, int $shopId, string $hostname, int $adminUserId): array
    {
        $hostname = strtolower(rtrim(trim($hostname), '.'));
        if (filter_var('https://' . $hostname, FILTER_VALIDATE_URL) === false || str_contains($hostname, '/') || str_contains($hostname, ':')) {
            throw new DomainException('Enter a hostname without a scheme, port, or path.');
        }
        $token = bin2hex(random_bytes(32));
        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s.u');
        $this->database->transException(true)->transStart();
        $this->database->table('shop_domains')->insert(['public_id'=>$publicId,'tenant_id'=>$tenantId,'shop_id'=>$shopId,'hostname'=>$hostname,'domain_type'=>'CUSTOM','verification_status'=>'PENDING','is_active'=>0,'is_primary'=>0,'verification_method'=>'DNS_TXT','verification_token_hash'=>hash('sha256',$token,true),'certificate_status'=>'PENDING','created_by_admin_user_id'=>$adminUserId,'updated_by_user_id'=>$adminUserId,'created_at'=>$now,'updated_at'=>$now]);
        $this->audit->record(['tenant_id'=>$tenantId,'shop_id'=>$shopId,'actor_type'=>'USER','actor_user_id'=>$adminUserId,'action_code'=>'domain.requested','category'=>'TENANT','resource_type'=>'shop_domain','resource_public_id'=>$publicId,'reason'=>'Custom domain onboarding requested','change_metadata'=>['hostname'=>$hostname]]);
        $this->database->transComplete();

        return ['public_id'=>$publicId,'hostname'=>$hostname,'dns_txt_token'=>$token];
    }

    public function activateVerified(int $domainId, int $adminUserId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required.');
        }
        $domain = $this->database->table('shop_domains')->where('id',$domainId)->get()->getRowArray();
        if (! $domain || $domain['verification_status'] !== 'VERIFIED' || $domain['certificate_status'] !== 'ACTIVE') {
            throw new DomainException('The domain must be verified and TLS-ready before activation.');
        }
        $now = gmdate('Y-m-d H:i:s.u');
        $this->database->transException(true)->transStart();
        $this->database->table('shop_domains')->where(['tenant_id'=>$domain['tenant_id'],'shop_id'=>$domain['shop_id'],'is_primary'=>1])->update(['is_primary'=>0,'updated_at'=>$now]);
        $this->database->table('shop_domains')->where('id',$domainId)->update(['is_active'=>1,'is_primary'=>1,'activated_at'=>$now,'updated_by_user_id'=>$adminUserId,'updated_at'=>$now]);
        $this->audit->record(['tenant_id'=>$domain['tenant_id'],'shop_id'=>$domain['shop_id'],'actor_type'=>'USER','actor_user_id'=>$adminUserId,'action_code'=>'domain.activated','category'=>'TENANT','resource_type'=>'shop_domain','resource_public_id'=>$domain['public_id'],'reason'=>$reason]);
        $this->database->transComplete();
    }
}
