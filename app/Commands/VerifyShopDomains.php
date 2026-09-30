<?php
declare(strict_types=1);
namespace App\Commands;
use App\Modules\BrandingDomain\Application\DomainService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Config\Services;
use DomainException;
use Psr\Log\LoggerInterface;
final class VerifyShopDomains extends BaseCommand
{
 protected $group='DawaTrack';protected $name='domains:verify';protected $description='Verify pending custom-domain DNS TXT challenges using the application service.';
 public function __construct(LoggerInterface $logger,Commands $commands){parent::__construct($logger,$commands);}
 public function run(array $params):void{$db=db_connect();$service=new DomainService($db,Services::auditService());$domains=$db->table('shop_domains')->where(['domain_type'=>'CUSTOM','verification_status'=>'PENDING'])->get()->getResultArray();foreach($domains as $domain){try{$matched=$service->verify($domain);CLI::write($domain['hostname'].($matched?': verified':': pending'),$matched?'green':'yellow');}catch(DomainException $e){CLI::error($domain['hostname'].': '.$e->getMessage());}}}
}
