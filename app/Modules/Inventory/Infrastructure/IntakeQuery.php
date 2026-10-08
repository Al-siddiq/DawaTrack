<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class IntakeQuery{
 public function __construct(private readonly BaseConnection $database) {
}
 /** @return list<array<string,mixed>> */ public function listing(ShopContext $c): array{return $this->database->table('stock_intakes si')->select('si.public_id,si.intake_kind,si.status,si.reference,si.occurred_at,si.posted_at,original.public_id original_intake_public_id,reversal.public_id reversal_public_id,replacement.public_id replacement_public_id')->join('stock_intakes original','original.id=si.reversal_of_intake_id','left')->join('stock_intakes reversal','reversal.reversal_of_intake_id=si.id','left')->join('stock_intakes replacement','replacement.replacement_for_intake_id=si.id','left')->where(['si.tenant_id'=>$c->tenantId,'si.shop_id'=>$c->shopId])->orderBy('si.created_at','DESC')->get(50)->getResultArray();
}
 /** @return array<string,mixed> */ public function detail(ShopContext $c,string $id): array{$h=$this->database->table('stock_intakes si')->select('si.id,si.public_id,si.intake_kind,si.status,si.reference,si.notes,si.occurred_at,si.device_local_offset_minutes,si.posted_at,si.created_at,si.updated_at,si.reversal_of_intake_id,original.public_id original_intake_public_id,reversal.public_id reversal_public_id,replacement.public_id replacement_public_id')->join('stock_intakes original','original.id=si.reversal_of_intake_id','left')->join('stock_intakes reversal','reversal.reversal_of_intake_id=si.id','left')->join('stock_intakes replacement','replacement.replacement_for_intake_id=si.id','left')->where(['si.tenant_id'=>$c->tenantId,'si.shop_id'=>$c->shopId,'si.public_id'=>strtolower($id)])->get()->getRowArray();
if(!$h)throw new ApiException('INTAKE_NOT_FOUND','The Stock Intake was not found.',404);
$sourceIntakeId=$h['reversal_of_intake_id']??$h['id'];
$h['items']=$this->items($c,(int)$sourceIntakeId);
$h['movements']=$h['status']==='POSTED'?$this->intakeMovements($c,(int)$sourceIntakeId,$h['reversal_of_intake_id']!==null):[];
$h['can_reverse']=$h['status']==='POSTED'&&$h['reversal_of_intake_id']===null&&$h['reversal_public_id']===null&&$this->isCurrentSuffix($c,(int)$h['id']);
unset($h['id'],$h['reversal_of_intake_id']);
return $h;
}
 /** @return list<array<string,mixed>> */ private function items(ShopContext $c,int $intake): array{return $this->database->table('stock_intake_items i')->select('i.public_id,i.quantity,i.unit_cost,i.line_cost_amount,i.line_number,p.public_id product_public_id,p.name product_name,p.unit_name')->join('products p','p.tenant_id=i.tenant_id AND p.id=i.product_id')->where(['i.tenant_id'=>$c->tenantId,'i.shop_id'=>$c->shopId,'i.stock_intake_id'=>$intake])->orderBy('i.line_number','ASC')->get()->getResultArray();
}
 /** @return list<array<string,mixed>> */ private function intakeMovements(ShopContext $c,int $intake,bool $reversal): array{$builder=$this->database->table('stock_movements m')->select('m.public_id,m.movement_type,m.quantity_delta,m.unit_cost_basis,m.quantity_before,m.quantity_after,m.average_cost_before,m.average_cost_after,m.reason_text,m.occurred_at,m.posted_at,p.public_id product_public_id,p.name product_name,p.unit_name,u.username actor_name,original.public_id original_movement_public_id,reversed.public_id reversal_public_id')->join('products p','p.tenant_id=m.tenant_id AND p.id=m.product_id')->join('users u','u.id=m.created_by_user_id')->join('stock_movements original','original.id=m.reversal_of_movement_id','left')->join('stock_movements reversed','reversed.reversal_of_movement_id=m.id','left');
if($reversal)$builder->join('stock_intake_items i','i.id=original.stock_intake_item_id')->where('i.stock_intake_id',$intake);
else $builder->join('stock_intake_items i','i.id=m.stock_intake_item_id')->where('i.stock_intake_id',$intake);
return $builder->where(['m.tenant_id'=>$c->tenantId,'m.shop_id'=>$c->shopId])->orderBy('m.id','ASC')->get()->getResultArray();
}
 /** @return list<array<string,mixed>> */ public function productHistory(ShopContext $c,string $productPublicId): array{$product=$this->database->table('products')->select('id')->where(['tenant_id'=>$c->tenantId,'public_id'=>strtolower($productPublicId)])->get()->getRowArray();
if(!$product)throw new ApiException('PRODUCT_NOT_FOUND','The Product was not found.',404);
return $this->database->table('stock_movements m')->select('m.public_id,m.movement_type,m.quantity_delta,m.unit_cost_basis,m.quantity_before,m.quantity_after,m.average_cost_before,m.average_cost_after,m.reason_text,m.occurred_at,m.posted_at,u.username actor_name,original.public_id original_movement_public_id,reversed.public_id reversal_public_id')->join('users u','u.id=m.created_by_user_id')->join('stock_movements original','original.id=m.reversal_of_movement_id','left')->join('stock_movements reversed','reversed.reversal_of_movement_id=m.id','left')->where(['m.tenant_id'=>$c->tenantId,'m.shop_id'=>$c->shopId,'m.product_id'=>$product['id']])->orderBy('m.posted_at','DESC')->orderBy('m.id','DESC')->get(50)->getResultArray();
}
 private function isCurrentSuffix(ShopContext $c,int $intakeId): bool{$rows=$this->database->table('stock_movements m')->select('m.id,m.product_id,reversed.id reversal_id')->join('stock_intake_items i','i.id=m.stock_intake_item_id')->join('stock_movements reversed','reversed.reversal_of_movement_id=m.id','left')->where(['m.tenant_id'=>$c->tenantId,'m.shop_id'=>$c->shopId,'i.stock_intake_id'=>$intakeId])->get()->getResultArray();
if(!$rows)return false;
$max=[];
foreach($rows as $row) {
if($row['reversal_id']!==null)return false;
$pid=(int)$row['product_id'];
$max[$pid]=max($max[$pid]??0,(int)$row['id']);
}foreach($max as $pid=>$last) {
if($this->database->table('stock_movements')->where(['tenant_id'=>$c->tenantId,'shop_id'=>$c->shopId,'product_id'=>$pid])->where('id >',$last)->countAllResults())return false;
}return true;
}

 /** @return array<string,mixed> */ public function movement(ShopContext $c,string $publicId): array{$row=$this->database->table('stock_movements m')->select('m.public_id,m.movement_type,m.source_kind,m.quantity_delta,m.unit_cost_basis,m.quantity_before,m.quantity_after,m.average_cost_before,m.average_cost_after,m.reason_text,m.occurred_at,m.posted_at,p.public_id product_public_id,p.name product_name,p.unit_name,p.status product_status,u.username actor_name,original.public_id original_movement_public_id,reversed.public_id reversal_public_id')->join('products p','p.id=m.product_id AND p.tenant_id=m.tenant_id')->join('users u','u.id=m.created_by_user_id')->join('stock_movements original','original.id=m.reversal_of_movement_id','left')->join('stock_movements reversed','reversed.reversal_of_movement_id=m.id','left')->where(['m.tenant_id'=>$c->tenantId,'m.shop_id'=>$c->shopId,'m.public_id'=>strtolower($publicId)])->get()->getRowArray();
if(!$row)throw new ApiException('MOVEMENT_NOT_FOUND','The Stock Movement was not found.',404);
$row['can_reverse']=$row['source_kind']==='MANUAL'&&$row['movement_type']!=='REVERSAL'&&$row['reversal_public_id']===null;
return $row;
}
}
