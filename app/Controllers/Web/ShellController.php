<?php

declare(strict_types=1);
namespace App\Controllers\Web;
use App\Controllers\BaseController;
use App\Modules\BrandingDomain\Application\BrandResolver;
use Throwable;
final class ShellController extends BaseController {
 private function presentation(string $pageTitle): array { $brand=null; try{$brand=(new BrandResolver(db_connect()))->resolve($this->request->getServer('HTTP_HOST')??'');}catch(Throwable){$brand=null;} return ['pageTitle'=>$pageTitle,'brand'=>$brand]; }
 public function shop(): string { return view('shop/index',$this->presentation('Shop')); }
 public function authentication(): string { return view('auth/index',$this->presentation('Authentication')); }
 public function admin(): string { return view('admin/index',['pageTitle'=>'Platform Administration','brand'=>null]); }
 public function manifest(){ $data=$this->presentation('Shop');$brand=$data['brand'];return $this->response->setContentType('application/manifest+json')->setJSON(['id'=>'/','name'=>$brand['pwa_full_name']??'DawaTrack','short_name'=>$brand['pwa_short_name']??'DawaTrack','start_url'=>'/','scope'=>'/','display'=>'standalone','background_color'=>$brand['background_colour']??'#F4F7F5','theme_color'=>$brand['theme_colour']??'#155D46']); }
}
