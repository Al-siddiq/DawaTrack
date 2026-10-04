<?php

declare(strict_types=1);

namespace App\Modules\BrandingDomain\Application;

use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\AuditActor;
use App\Modules\Audit\Domain\AuditAppendCommand;
use App\Modules\Audit\Domain\AuditCategory;
use App\Support\Uuid;
use App\Support\SensitiveReason;
use CodeIgniter\Database\BaseConnection;
use DomainException;

final class DomainService
{
    public function __construct(
        private BaseConnection $db,
        private AuditService $audit,
        private ?HostnamePolicy $policy = null,
    ) {
        $this->policy ??= new HostnamePolicy();
    }

    public function requestCustom(int $tenantId, int $shopId, string $input, int $ownerId, ?int $membershipId = null): array
    {
        $hostname = $this->policy->normalize($input);
        $token = bin2hex(random_bytes(32));
        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s.u');
        $this->db->transException(true)->transStart();
        $this->db->table('shop_domains')->insert(['public_id'=>$publicId,'tenant_id'=>$tenantId,'shop_id'=>$shopId,'hostname'=>$hostname,'domain_type'=>'CUSTOM','verification_status'=>'PENDING','is_active'=>0,'is_primary'=>0,'verification_method'=>'DNS_TXT','verification_token_hash'=>hash('sha256',$token,true),'verification_reference'=>$this->policy->recordName($hostname),'certificate_status'=>'PENDING','created_by_admin_user_id'=>null,'updated_by_user_id'=>$ownerId,'created_at'=>$now,'updated_at'=>$now]);
        $this->record($tenantId,$shopId,$ownerId,$membershipId,'domain.requested',$publicId,'Custom domain requested',['hostname'=>$hostname,'record_name'=>$this->policy->recordName($hostname)]);
        $this->db->transComplete();
        return $this->challengeResponse($publicId,$hostname,$token);
    }

    public function rotate(int $tenantId, int $shopId, string $publicId, int $ownerId, int $membershipId): array
    {
        $domain=$this->scoped($tenantId,$shopId,$publicId); if($domain['domain_type']!=='CUSTOM'||$domain['is_active']) throw new DomainException('Only an inactive custom domain challenge can be rotated.');
        $token=bin2hex(random_bytes(32)); $now=gmdate('Y-m-d H:i:s.u');
        $this->db->transException(true)->transStart();
        $this->db->table('shop_domains')->where('id',$domain['id'])->update(['verification_status'=>'PENDING','verification_token_hash'=>hash('sha256',$token,true),'verified_at'=>null,'verified_by_admin_user_id'=>null,'updated_by_user_id'=>$ownerId,'updated_at'=>$now]);
        $this->record($tenantId,$shopId,$ownerId,$membershipId,'domain.challenge_rotated',$publicId,'DNS ownership challenge rotated'); $this->db->transComplete();
        return $this->challengeResponse($publicId,$domain['hostname'],$token);
    }

    public function verify(array $domain, ?AuditActor $actor = null): bool
    {
        if ($domain['domain_type'] !== 'CUSTOM' || $domain['verification_status'] === 'VERIFIED') {
            return $domain['verification_status'] === 'VERIFIED';
        }

        $recordName = $domain['verification_reference'] ?: $this->policy->recordName($domain['hostname']);
        $records = @dns_get_record($recordName, DNS_TXT);
        if ($records === false) {
            throw new DomainException('DNS lookup failed; verification can be retried.');
        }

        $matched = false;
        foreach ($records as $record) {
            $value = $record['txt'] ?? implode('', $record['entries'] ?? []);
            if (is_string($value) && hash_equals($domain['verification_token_hash'], hash('sha256', $value, true))) {
                $matched = true;
                break;
            }
        }
        if (! $matched) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s.u');
        $this->db->transException(true)->transStart();
        $this->db->table('shop_domains')->where([
            'id' => $domain['id'],
            'verification_status' => 'PENDING',
        ])->update([
            'verification_status' => 'VERIFIED',
            'verified_at' => $now,
            'verified_by_admin_user_id' => null,
            'updated_by_user_id' => $actor?->userId ?? $domain['updated_by_user_id'],
            'updated_at' => $now,
        ]);
        $this->audit->append(new AuditAppendCommand(
            actor: $actor ?? AuditActor::system((int) $domain['tenant_id'], (int) $domain['shop_id']),
            actionCode: 'domain.verified',
            category: AuditCategory::TENANT,
            resourceType: 'shop_domain',
            resourcePublicId: $domain['public_id'],
            reason: 'DNS TXT ownership challenge matched',
        ));
        $this->db->transComplete();

        return true;
    }

    public function tls(string $publicId, int $adminId, bool $ready, string $reason): void { $this->transition($publicId,$adminId,$reason,function(array $d,string $now)use($ready){if($d['domain_type']!=='CUSTOM'||$d['verification_status']!=='VERIFIED')throw new DomainException('Ownership must be verified before TLS status changes.');return ['certificate_status'=>$ready?'ACTIVE':'FAILED','updated_at'=>$now];},$ready?'domain.tls_ready':'domain.tls_failed'); }
    public function activate(string $publicId,int $adminId,string $reason):void{$this->transition($publicId,$adminId,$reason,function(array $d,string $now){if($d['verification_status']!=='VERIFIED'||$d['certificate_status']!=='ACTIVE')throw new DomainException('The domain must be verified and TLS-ready before activation.');return ['is_active'=>1,'activated_at'=>$now,'deactivated_at'=>null,'updated_at'=>$now];},'domain.activated');}
    public function primary(string $publicId,int $adminId,string $reason):void{$this->transition($publicId,$adminId,$reason,function(array $d,string $now){if(!$d['is_active'])throw new DomainException('Only an active domain can be primary.');$this->db->table('shop_domains')->where(['tenant_id'=>$d['tenant_id'],'shop_id'=>$d['shop_id'],'is_primary'=>1])->update(['is_primary'=>0,'updated_at'=>$now]);return ['is_primary'=>1,'updated_at'=>$now];},'domain.primary_switched');}
    public function deactivate(string $publicId,int $adminId,string $reason):void{$this->transition($publicId,$adminId,$reason,function(array $d,string $now){if($d['domain_type']!=='CUSTOM')throw new DomainException('The platform fallback cannot be deactivated.');if($d['is_primary'])throw new DomainException('Switch to the platform fallback before deactivation.');return ['is_active'=>0,'is_primary'=>0,'deactivated_at'=>$now,'updated_at'=>$now];},'domain.deactivated');}
    public function fallback(string $publicId,int $adminId,string $reason):void{$this->transition($publicId,$adminId,$reason,function(array $d,string $now){if($d['domain_type']!=='PLATFORM_SUBDOMAIN'||!$d['is_active'])throw new DomainException('An active platform domain is required.');$this->db->table('shop_domains')->where(['tenant_id'=>$d['tenant_id'],'shop_id'=>$d['shop_id'],'is_primary'=>1])->update(['is_primary'=>0,'updated_at'=>$now]);return ['is_primary'=>1,'updated_at'=>$now];},'domain.platform_fallback');}

    private function transition(string $id, int $actor, string $reason, callable $changes, string $action): void
    {
        $reason = SensitiveReason::require($reason);
        $domain = $this->db->table('shop_domains')->where('public_id', $id)->get()->getRowArray();
        if ($domain === null) {
            throw new DomainException('Domain not found.');
        }

        $now = gmdate('Y-m-d H:i:s.u');
        $this->db->transException(true)->transStart();
        $update = $changes($domain, $now) + ['updated_by_user_id' => $actor];
        $this->db->table('shop_domains')->where('id', $domain['id'])->update($update);
        $this->audit->append(new AuditAppendCommand(
            actor: AuditActor::platformUser($actor, (int) $domain['tenant_id'], (int) $domain['shop_id']),
            actionCode: $action,
            category: AuditCategory::TENANT,
            resourceType: 'shop_domain',
            resourcePublicId: $id,
            reason: $reason,
            metadata: ['hostname' => $domain['hostname']],
        ));
        $this->db->transComplete();
    }
    private function scoped(int $t,int $s,string $id):array{$d=$this->db->table('shop_domains')->where(['tenant_id'=>$t,'shop_id'=>$s,'public_id'=>$id])->get()->getRowArray();if(!$d)throw new DomainException('Domain not found.');return $d;}
    private function challengeResponse(string $id,string $host,string $token):array{return ['public_id'=>$id,'hostname'=>$host,'dns_record_name'=>$this->policy->recordName($host),'dns_record_type'=>'TXT','dns_record_value'=>$token];}
    private function record(
        int $tenantId,
        int $shopId,
        int $userId,
        ?int $membershipId,
        string $action,
        string $resourcePublicId,
        string $reason,
        array $metadata = [],
    ): void
    {
        if ($membershipId === null) {
            throw new DomainException('Tenant Audit authority is incomplete.');
        }
        $this->audit->append(new AuditAppendCommand(
            actor: AuditActor::tenantUser($userId, $membershipId, $tenantId, $shopId),
            actionCode: $action,
            category: AuditCategory::TENANT,
            resourceType: 'shop_domain',
            resourcePublicId: $resourcePublicId,
            reason: $reason,
            metadata: $metadata,
        ));
    }
}
