<?php

declare(strict_types=1);

namespace App\Modules\BrandingDomain\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;
use Throwable;

final class BrandingService
{
    private const MIME_EXTENSIONS=['image/png'=>'png','image/jpeg'=>'jpg'];
    private const LIMITS=[
        'LOGO'=>[2097152,120,40,2400,1200,.2,8.0],
        'FAVICON'=>[524288,32,32,512,512,1.0,1.0],
        'LOGIN_BACKGROUND'=>[5242880,800,450,5000,5000,.8,3.0],
        'PWA_ICON_192'=>[1048576,192,192,192,192,1.0,1.0],
        'PWA_ICON_512'=>[2097152,512,512,512,512,1.0,1.0],
    ];
    private const LENGTHS=['brand_display_name'=>160,'tagline'=>180,'public_description'=>500,'pwa_full_name'=>80,'pwa_short_name'=>24,'application_title'=>100];
    private const REQUIRED=['brand_display_name','pwa_full_name','pwa_short_name','application_title'];
    private const COLOURS=['primary_colour','secondary_colour','accent_colour','theme_colour','background_colour'];

    public function __construct(private BaseConnection $db,private AuditService $audit){}

    public function updateProfile(int $tenantId,int $shopId,int $actorUserId,int $membershipId,array $input):void
    {
        $errors=[];$clean=[];
        foreach(self::LENGTHS as $field=>$max){$value=trim((string)($input[$field]??''));if(in_array($field,self::REQUIRED,true)&&$value==='')$errors[$field][]='This field is required.';if(mb_strlen($value)>$max)$errors[$field][]="Use {$max} characters or fewer.";$clean[$field]=$value===''?null:$value;}
        foreach(self::COLOURS as $field){$value=strtoupper(trim((string)($input[$field]??'')));if(preg_match('/^#[0-9A-F]{6}$/',$value)!==1)$errors[$field][]='Use #RRGGBB format.';$clean[$field]=$value;}
        if(!$errors && !$this->contrastPasses($clean['primary_colour'],$clean['background_colour']))$errors['primary_colour'][]='Primary and background colours must have a WCAG contrast ratio of at least 4.5:1.';
        if($errors)throw new ApiException('VALIDATION_FAILED','Correct the highlighted branding fields.',422,$errors);
        $profile=$this->db->table('shop_branding_profiles')->where(['tenant_id'=>$tenantId,'shop_id'=>$shopId])->get()->getRowArray();if(!$profile)throw new DomainException('Branding profile was not found.');
        $before=[];$after=[];foreach($clean as $field=>$value){if(($profile[$field]??null)!==$value){$before[$field]=$profile[$field]??null;$after[$field]=$value;}}
        if(!$after)throw new ApiException('CONFLICT','No branding values changed.',409);
        $version=(int)$profile['configuration_version']+1;$update=$after+['configuration_version'=>$version,'updated_by_user_id'=>$actorUserId,'updated_at'=>gmdate('Y-m-d H:i:s.u')];
        $this->db->transException(true)->transStart();$this->db->table('shop_branding_profiles')->where('id',$profile['id'])->update($update);
        $this->audit->record(['tenant_id'=>$tenantId,'shop_id'=>$shopId,'actor_type'=>'USER','actor_user_id'=>$actorUserId,'actor_membership_id'=>$membershipId,'action_code'=>'branding.updated','category'=>'TENANT','resource_type'=>'shop_branding_profile','resource_public_id'=>$profile['public_id'],'change_metadata'=>['fields'=>array_keys($after),'before'=>$before,'after'=>$after,'configuration_version'=>$version]]);$this->db->transComplete();
    }

    public function replaceAsset(int $tenantId,int $shopId,int $actorUserId,int $membershipId,string $type,?UploadedFile $file):array
    {
        if(!isset(self::LIMITS[$type])||!$file||!$file->isValid())throw new DomainException('Select a valid supported branding asset.');
        [$max,$minW,$minH,$maxW,$maxH,$minRatio,$maxRatio]=self::LIMITS[$type];if($file->getSize()<=0||$file->getSize()>$max)throw new DomainException('The file exceeds the byte limit for this asset type.');
        $source=$file->getTempName();$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($source);$size=@getimagesize($source);if(!isset(self::MIME_EXTENSIONS[$mime])||$size===false||($size['mime']??'')!==$mime)throw new DomainException('Only genuine decoded PNG or JPEG images are accepted.');
        [$w,$h]=[$size[0],$size[1]];$ratio=$w/$h;if($w<$minW||$h<$minH||$w>$maxW||$h>$maxH||$w*$h>16000000||$ratio<$minRatio||$ratio>$maxRatio)throw new DomainException('The image dimensions or aspect ratio are not valid for this asset type.');
        $directory=WRITEPATH."uploads/branding/{$tenantId}/{$shopId}";if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new DomainException('Secure asset storage is unavailable.');
        $opaque=bin2hex(random_bytes(24)).'.'.self::MIME_EXTENSIONS[$mime];$temporary=$directory.'/.'.$opaque.'.tmp';$final=$directory.'/'.$opaque;$relative="{$tenantId}/{$shopId}/{$opaque}";
        $this->normalize($source,$temporary,$mime);$bytes=file_get_contents($temporary);if($bytes===false){@unlink($temporary);throw new DomainException('The normalized image could not be read.');}
        $publicId=Uuid::v4();$now=gmdate('Y-m-d H:i:s.u');$old=[];
        try{$this->db->transException(true)->transStart();$old=$this->db->table('shop_brand_assets')->select('id')->where(['tenant_id'=>$tenantId,'shop_id'=>$shopId,'asset_type'=>$type,'is_active'=>1])->get()->getResultArray();$this->db->table('shop_brand_assets')->where(['tenant_id'=>$tenantId,'shop_id'=>$shopId,'asset_type'=>$type,'is_active'=>1])->update(['is_active'=>0,'deactivated_at'=>$now]);$this->db->table('shop_brand_assets')->insert(['public_id'=>$publicId,'tenant_id'=>$tenantId,'shop_id'=>$shopId,'asset_type'=>$type,'storage_key'=>$relative,'mime_type'=>$mime,'width_px'=>$w,'height_px'=>$h,'file_size_bytes'=>strlen($bytes),'content_sha256'=>hash('sha256',$bytes,true),'is_active'=>1,'uploaded_by_user_id'=>$actorUserId,'activated_at'=>$now,'created_at'=>$now]);$this->audit->record(['tenant_id'=>$tenantId,'shop_id'=>$shopId,'actor_type'=>'USER','actor_user_id'=>$actorUserId,'actor_membership_id'=>$membershipId,'action_code'=>'branding.asset_replaced','category'=>'TENANT','resource_type'=>'shop_brand_asset','resource_public_id'=>$publicId,'change_metadata'=>['asset_type'=>$type,'mime_type'=>$mime,'width'=>$w,'height'=>$h,'bytes'=>strlen($bytes)]]);$this->db->transComplete();if(!rename($temporary,$final))throw new DomainException('The asset could not be finalized.');}
        catch(Throwable $e){@unlink($temporary);@unlink($final);if($this->db->transStatus()){try{$this->db->transException(true)->transStart();$this->db->table('shop_brand_assets')->where('public_id',$publicId)->delete();foreach($old as $row)$this->db->table('shop_brand_assets')->where('id',$row['id'])->update(['is_active'=>1,'deactivated_at'=>null]);$this->db->transComplete();}catch(Throwable){}}throw $e;}
        return ['public_id'=>$publicId,'asset_type'=>$type,'width_px'=>$w,'height_px'=>$h];
    }

    private function normalize(string $source,string $target,string $mime):void
    {
        if(!function_exists('imagecreatefrompng'))throw new DomainException('Server image normalization support is unavailable.');
        $image=$mime==='image/png'?@imagecreatefrompng($source):@imagecreatefromjpeg($source);if(!$image)throw new DomainException('The image could not be safely decoded.');
        if($mime==='image/png'){imagealphablending($image,false);imagesavealpha($image,true);$ok=imagepng($image,$target,9);}else{$ok=imagejpeg($image,$target,88);}imagedestroy($image);if(!$ok){@unlink($target);throw new DomainException('The image could not be normalized.');}chmod($target,0600);
    }
    private function contrastPasses(string $a,string $b):bool{$l=function(string $c):float{$v=[];foreach([1,3,5]as$i){$n=hexdec(substr($c,$i,2))/255;$v[]=$n<=.04045?$n/12.92:(($n+.055)/1.055)**2.4;}return .2126*$v[0]+.7152*$v[1]+.0722*$v[2];};$x=$l($a);$y=$l($b);return(max($x,$y)+.05)/(min($x,$y)+.05)>=4.5;}
}
