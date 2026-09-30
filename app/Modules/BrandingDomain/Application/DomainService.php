<?php

declare(strict_types=1);

namespace App\Modules\BrandingDomain\Application;

use App\Modules\Audit\Application\AuditService;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use DomainException;

final class DomainService
{
    public function __construct(private BaseConnection $db, private AuditService $audit, private ?HostnamePolicy $policy = null) { $this->policy ??= new HostnamePolicy(); }

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

    public function verify(array $domain, string $actorType='SYSTEM', ?int $actorId=null): bool
    {
        if ($domain['domain_type']!=='CUSTOM' || $domain['verification_status']==='VERIFIED') return $domain['verification_status']==='VERIFIED';
        $records=@dns_get_record($domain['verification_reference'] ?: $this->policy->recordName($domain['hostname']), DNS_TXT);
        if ($records===false) throw new DomainException('DNS lookup failed; verification can be retried.');
        $matched=false; foreach($records as $record){$value=$record['txt']??implode('', $record['entries']??[]);if(is_string($value)&&hash_equals($domain['verification_token_hash'],hash('sha256',$value,true))){$matched=true;break;}}
        if(!$matched) return false;
        $now=gmdate('Y-m-d H:i:s.u'); $this->db->transException(true)->transStart();
        $this->db->table('shop_domains')->where(['id'=>$domain['id'],'verification_status'=>'PENDING'])->update(['verification_status'=>'VERIFIED','verified_at'=>$now,'verified_by_admin_user_id'=>null,'updated_by_user_id'=>$actorId??$domain['updated_by_user_id'],'updated_at'=>$now]);
        $this->audit->record(['tenant_id'=>$domain['tenant_id'],'shop_id'=>$domain['shop_id'],'actor_type'=>$actorType,'actor_user_id'=>$actorId,'action_code'=>'domain.verified','category'=>'TENANT','resource_type'=>'shop_domain','resource_public_id'=>$domain['public_id'],'reason'=>'DNS TXT ownership challenge matched']); $this->db->transComplete(); return true;
    }

    public function tls(string $publicId, int $adminId, bool $ready, string $reason): void { $this->transition($publicId,$adminId,$reason,function(array $d,string $now)use($ready){if($d['domain_type']!=='CUSTOM'||$d['verification_status']!=='VERIFIED')throw new DomainException('Ownership must be verified before TLS status changes.');return ['certificate_status'=>$ready?'ACTIVE':'FAILED','updated_at'=>$now];},$ready?'domain.tls_ready':'domain.tls_failed'); }
    public function activate(string $publicId,int $adminId,string $reason):void{$this->transition($publicId,$adminId,$reason,function(array $d,string $now){if($d['verification_status']!=='VERIFIED'||$d['certificate_status']!=='ACTIVE')throw new DomainException('The domain must be verified and TLS-ready before activation.');return ['is_active'=>1,'activated_at'=>$now,'deactivated_at'=>null,'updated_at'=>$now];},'domain.activated');}
    public function primary(string $publicId,int $adminId,string $reason):void{$this->transition($publicId,$adminId,$reason,function(array $d,string $now){if(!$d['is_active'])throw new DomainException('Only an active domain can be primary.');$this->db->table('shop_domains')->where(['tenant_id'=>$d['tenant_id'],'shop_id'=>$d['shop_id'],'is_primary'=>1])->update(['is_primary'=>0,'updated_at'=>$now]);return ['is_primary'=>1,'updated_at'=>$now];},'domain.primary_switched');}
    public function deactivate(string $publicId,int $adminId,string $reason):void{$this->transition($publicId,$adminId,$reason,function(array $d,string $now){if($d['domain_type']!=='CUSTOM')throw new DomainException('The platform fallback cannot be deactivated.');if($d['is_primary'])throw new DomainException('Switch to the platform fallback before deactivation.');return ['is_active'=>0,'is_primary'=>0,'deactivated_at'=>$now,'updated_at'=>$now];},'domain.deactivated');}
    public function fallback(string $publicId,int $adminId,string $reason):void{$this->transition($publicId,$adminId,$reason,function(array $d,string $now){if($d['domain_type']!=='PLATFORM_SUBDOMAIN'||!$d['is_active'])throw new DomainException('An active platform domain is required.');$this->db->table('shop_domains')->where(['tenant_id'=>$d['tenant_id'],'shop_id'=>$d['shop_id'],'is_primary'=>1])->update(['is_primary'=>0,'updated_at'=>$now]);return ['is_primary'=>1,'updated_at'=>$now];},'domain.platform_fallback');}

    private function transition(string $id,int $actor,string $reason,callable $changes,string $action):void{if(trim($reason)==='')throw new DomainException('A reason is required.');$d=$this->db->table('shop_domains')->where('public_id',$id)->get()->getRowArray();if(!$d)throw new DomainException('Domain not found.');$now=gmdate('Y-m-d H:i:s.u');$this->db->transException(true)->transStart();$update=$changes($d,$now)+['updated_by_user_id'=>$actor];$this->db->table('shop_domains')->where('id',$d['id'])->update($update);$this->record($d['tenant_id'],$d['shop_id'],$actor,null,$action,$id,trim($reason),['hostname'=>$d['hostname']]);$this->db->transComplete();}
    private function scoped(int $t,int $s,string $id):array{$d=$this->db->table('shop_domains')->where(['tenant_id'=>$t,'shop_id'=>$s,'public_id'=>$id])->get()->getRowArray();if(!$d)throw new DomainException('Domain not found.');return $d;}
    private function challengeResponse(string $id,string $host,string $token):array{return ['public_id'=>$id,'hostname'=>$host,'dns_record_name'=>$this->policy->recordName($host),'dns_record_type'=>'TXT','dns_record_value'=>$token];}
    private function record(int $t,int $s,int $u,?int $m,string $a,string $r,string $reason,array $meta=[]):void{$this->audit->record(['tenant_id'=>$t,'shop_id'=>$s,'actor_type'=>'USER','actor_user_id'=>$u,'actor_membership_id'=>$m,'action_code'=>$a,'category'=>'TENANT','resource_type'=>'shop_domain','resource_public_id'=>$r,'reason'=>$reason,'change_metadata'=>$meta]);}
}
