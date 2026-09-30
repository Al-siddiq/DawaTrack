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
 public function manifest(){ $data=$this->presentation('Shop');$brand=$data['brand'];$manifest=['id'=>'/','name'=>$brand['pwa_full_name']??'DawaTrack','short_name'=>$brand['pwa_short_name']??'DawaTrack','description'=>$brand['public_description']??'Secure Shop operations','start_url'=>'/','scope'=>'/','display'=>'standalone','background_color'=>$brand['background_colour']??'#F4F7F5','theme_color'=>$brand['theme_colour']??'#155D46'];$icons=[];foreach([192,512]as$size){$key='PWA_ICON_'.$size;if(!empty($brand['assets'][$key]))$src=$brand['assets'][$key];else{$path=FCPATH.'assets/icons/pwa-'.$size.'.png';$src=is_file($path)?base_url('assets/icons/pwa-'.$size.'.png'):null;}if($src)$icons[]=['src'=>$src,'sizes'=>$size.'x'.$size,'type'=>'image/png','purpose'=>'any maskable'];}if($icons)$manifest['icons']=$icons;return $this->response->setHeader('Cache-Control','public, max-age=300, must-revalidate')->setContentType('application/manifest+json')->setJSON($manifest); }
}
