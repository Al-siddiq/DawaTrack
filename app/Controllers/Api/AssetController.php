<?php

declare(strict_types=1);
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Exceptions\PageNotFoundException;
final class AssetController extends BaseController { public function show(string $publicId){$c=ShopContext::resolve(db_connect(),(int)auth()->id(),$this->request->getServer('HTTP_HOST')??'');$asset=db_connect()->table('shop_brand_assets')->where(['public_id'=>$publicId,'tenant_id'=>$c->tenantId,'shop_id'=>$c->shopId,'is_active'=>1])->get()->getRowArray();if(!$asset)throw PageNotFoundException::forPageNotFound();$path=WRITEPATH.'uploads/branding/'.$asset['storage_key'];if(!is_file($path))throw PageNotFoundException::forPageNotFound();return $this->response->setHeader('Content-Type',$asset['mime_type'])->setHeader('X-Content-Type-Options','nosniff')->setHeader('Content-Disposition','inline; filename="brand-asset"')->setHeader('Cache-Control','private, max-age=3600')->setBody(file_get_contents($path));}}
