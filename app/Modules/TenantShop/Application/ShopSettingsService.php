<?php

declare(strict_types=1);
namespace App\Modules\TenantShop\Application;
use App\Modules\Audit\Application\AuditService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;
use DomainException;
final class ShopSettingsService {
 public function __construct(private BaseConnection $database, private AuditService $audit) {}
 public function get(ShopContext $context): array { return $this->database->table('shops')->select('public_id,name,contact_phone,contact_email,address_line_1,address_line_2,city_town,state_region,country_code,timezone_name,currency_code,status')->where(['id'=>$context->shopId,'tenant_id'=>$context->tenantId])->get()->getRowArray() ?? []; }
 public function update(ShopContext $context,array $input): array { $context->requireOwner(); $allowed=array_flip(['name','contact_phone','contact_email','address_line_1','address_line_2','city_town','state_region','timezone_name']); $data=array_intersect_key($input,$allowed); foreach(['name','address_line_1','city_town','state_region','timezone_name'] as $field) if(trim((string)($data[$field]??''))==='') throw new DomainException("{$field} is required."); if(($data['contact_email']??'')!==''&&!filter_var($data['contact_email'],FILTER_VALIDATE_EMAIL)) throw new DomainException('Contact email is invalid.'); $data['updated_at']=gmdate('Y-m-d H:i:s.u'); $shop=$this->get($context); $this->database->transException(true)->transStart(); $this->database->table('shops')->where(['id'=>$context->shopId,'tenant_id'=>$context->tenantId])->update($data); $this->audit->record(['tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'actor_type'=>'USER','actor_user_id'=>$context->userId,'actor_membership_id'=>$context->membershipId,'action_code'=>'shop.profile_updated','category'=>'TENANT','resource_type'=>'shop','resource_public_id'=>$shop['public_id'],'change_metadata'=>['fields'=>array_keys($data)]]); $this->database->transComplete(); return $this->get($context); }
}
