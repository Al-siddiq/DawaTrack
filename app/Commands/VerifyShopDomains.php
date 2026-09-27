<?php

declare(strict_types=1);
namespace App\Commands;
use App\Modules\Audit\Application\AuditService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Psr\Log\LoggerInterface;
final class VerifyShopDomains extends BaseCommand {
 protected $group='DawaTrack'; protected $name='domains:verify'; protected $description='Verify pending custom-domain DNS TXT challenges.';
 public function __construct(LoggerInterface $logger,Commands $commands){parent::__construct($logger,$commands);}
 public function run(array $params): void { $db=db_connect();$domains=$db->table('shop_domains')->where(['domain_type'=>'CUSTOM','verification_status'=>'PENDING'])->get()->getResultArray();foreach($domains as $domain){$records=dns_get_record($domain['hostname'],DNS_TXT);$matched=false;foreach($records as $record){$txt=$record['txt']??'';if(hash_equals($domain['verification_token_hash'],hash('sha256',$txt,true))){$matched=true;break;}}if(!$matched){CLI::write($domain['hostname'].': pending');continue;}$now=gmdate('Y-m-d H:i:s.u');$db->transException(true)->transStart();$db->table('shop_domains')->where('id',$domain['id'])->update(['verification_status'=>'VERIFIED','verified_at'=>$now,'updated_at'=>$now]);(new AuditService($db,service('requestContext')))->record(['tenant_id'=>$domain['tenant_id'],'shop_id'=>$domain['shop_id'],'actor_type'=>'SYSTEM','action_code'=>'domain.verified','category'=>'TENANT','resource_type'=>'shop_domain','resource_public_id'=>$domain['public_id'],'reason'=>'DNS TXT ownership challenge matched']);$db->transComplete();CLI::write($domain['hostname'].': verified','green');} }
}
