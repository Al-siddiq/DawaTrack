<?php
declare(strict_types=1);
namespace App\Commands;
use App\Modules\Subscription\Application\SubscriptionService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Config\Services;
use Psr\Log\LoggerInterface;
final class RecordSubscriptionExpiries extends BaseCommand
{
 protected $group='DawaTrack';protected $name='subscriptions:record-expiries';protected $description='Append repeat-safe evidence for time-derived entitlement expiries.';protected $usage='subscriptions:record-expiries <platform-admin-user-id>';
 public function __construct(LoggerInterface $logger,Commands $commands){parent::__construct($logger,$commands);}
 public function run(array $params):void{$actor=(int)($params[0]??0);if($actor<1||db_connect()->table('users')->where('id',$actor)->countAllResults()!==1){CLI::error('Provide a valid controlled Platform Administrator user ID.');return;}$count=(new SubscriptionService(db_connect(),Services::auditService()))->recordExpiries($actor);CLI::write("Recorded {$count} expiry event(s).",'green');}
}
