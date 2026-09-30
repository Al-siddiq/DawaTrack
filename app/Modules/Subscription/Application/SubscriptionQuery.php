<?php
declare(strict_types=1);
namespace App\Modules\Subscription\Application;
use CodeIgniter\Database\BaseConnection;
use App\Support\UtcTimestamp;
final class SubscriptionQuery
{
 public function __construct(private BaseConnection $db){}
 public function forTenant(int $tenantId):array
 {
  $now=UtcTimestamp::now();$rows=$this->db->table('subscriptions s')->select('s.public_id,s.entitlement_type,s.starts_at,s.ends_at,s.plan_code_snapshot,s.max_active_staff_snapshot,s.price_amount_snapshot,s.currency_code,s.external_reference,s.reason,s.created_at,p.name AS plan_name')->join('subscription_plans p','p.id=s.subscription_plan_id')->where('s.tenant_id',$tenantId)->orderBy('s.starts_at','ASC')->get()->getResultArray();
  $current=null;$historical=[];$future=[];foreach($rows as $row){if($row['starts_at']<=$now&&$row['ends_at']>$now)$current=$row;elseif($row['ends_at']<=$now)$historical[]=$row;else $future[]=$row;}
  $events=$this->db->table('subscription_events e')->select('e.public_id,e.event_type,e.effective_at,e.external_reference,e.reason,e.created_at,s.public_id AS subscription_public_id')->join('subscriptions s','s.id=e.subscription_id AND s.tenant_id=e.tenant_id')->where('e.tenant_id',$tenantId)->orderBy('e.effective_at','DESC')->get()->getResultArray();
  return ['current_entitlement'=>$current,'historical_periods'=>array_reverse($historical),'future_periods'=>$future,'events'=>$events];
 }
}
