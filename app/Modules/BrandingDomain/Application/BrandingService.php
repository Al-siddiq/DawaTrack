<?php

declare(strict_types=1);

namespace App\Modules\BrandingDomain\Application;

use App\Modules\Audit\Application\AuditService;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;

final class BrandingService
{
    private const MIME_EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/x-icon' => 'ico'];

    public function __construct(private BaseConnection $database, private AuditService $audit)
    {
    }

    public function updateProfile(int $tenantId, int $shopId, int $actorUserId, int $membershipId, array $input): void
    {
        foreach (['primary_colour','secondary_colour','accent_colour','theme_colour','background_colour'] as $field) {
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($input[$field] ?? '')) !== 1) {
                throw new DomainException('Every brand colour must use #RRGGBB format.');
            }
            $input[$field] = strtoupper($input[$field]);
        }
        $profile = $this->database->table('shop_branding_profiles')->where(['tenant_id' => $tenantId, 'shop_id' => $shopId])->get()->getRowArray();
        if (! $profile) {
            throw new DomainException('Branding profile was not found.');
        }
        $allowed = ['brand_display_name','tagline','public_description','primary_colour','secondary_colour','accent_colour','theme_colour','background_colour','pwa_full_name','pwa_short_name','application_title'];
        $changes = array_intersect_key($input, array_flip($allowed));
        $changes['configuration_version'] = (int) $profile['configuration_version'] + 1;
        $changes['updated_by_user_id'] = $actorUserId;
        $changes['updated_at'] = gmdate('Y-m-d H:i:s.u');
        $this->database->transException(true)->transStart();
        $this->database->table('shop_branding_profiles')->where('id', $profile['id'])->update($changes);
        $this->audit->record(['tenant_id'=>$tenantId,'shop_id'=>$shopId,'actor_type'=>'USER','actor_user_id'=>$actorUserId,'actor_membership_id'=>$membershipId,'action_code'=>'branding.updated','category'=>'TENANT','resource_type'=>'shop_branding_profile','resource_public_id'=>$profile['public_id'],'change_metadata'=>['configuration_version'=>$changes['configuration_version']]]);
        $this->database->transComplete();
    }

    public function replaceAsset(int $tenantId, int $shopId, int $actorUserId, int $membershipId, string $assetType, UploadedFile $file): array
    {
        if (! in_array($assetType, ['LOGO','FAVICON','PWA_ICON_192','PWA_ICON_512','LOGIN_BACKGROUND'], true) || ! $file->isValid() || $file->getSize() > 5_242_880) {
            throw new DomainException('The image type or file is invalid.');
        }
        $bytes = file_get_contents($file->getTempName());
        $dimensions = @getimagesize($file->getTempName());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getTempName());
        if ($bytes === false || $dimensions === false || ! isset(self::MIME_EXTENSIONS[$mime])) {
            throw new DomainException('The upload is not a supported decoded image.');
        }
        $required = $assetType === 'PWA_ICON_192' ? 192 : ($assetType === 'PWA_ICON_512' ? 512 : null);
        if ($required !== null && ($dimensions[0] !== $required || $dimensions[1] !== $required)) {
            throw new DomainException("{$assetType} must be exactly {$required} by {$required} pixels.");
        }
        $publicId = Uuid::v4();
        $relative = $tenantId . '/' . $shopId . '/' . bin2hex(random_bytes(24)) . '.' . self::MIME_EXTENSIONS[$mime];
        $directory = WRITEPATH . 'uploads/branding/' . $tenantId . '/' . $shopId;
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new DomainException('Secure asset storage is unavailable.');
        }
        if (file_put_contents(WRITEPATH . 'uploads/branding/' . $relative, $bytes, LOCK_EX) === false) {
            throw new DomainException('The image could not be stored.');
        }
        $now = gmdate('Y-m-d H:i:s.u');
        $this->database->transException(true)->transStart();
        $this->database->table('shop_brand_assets')->where(['tenant_id'=>$tenantId,'shop_id'=>$shopId,'asset_type'=>$assetType,'is_active'=>1])->update(['is_active'=>0,'deactivated_at'=>$now]);
        $this->database->table('shop_brand_assets')->insert(['public_id'=>$publicId,'tenant_id'=>$tenantId,'shop_id'=>$shopId,'asset_type'=>$assetType,'storage_key'=>$relative,'mime_type'=>$mime,'width_px'=>$dimensions[0],'height_px'=>$dimensions[1],'file_size_bytes'=>strlen($bytes),'content_sha256'=>hash('sha256',$bytes,true),'is_active'=>1,'uploaded_by_user_id'=>$actorUserId,'activated_at'=>$now,'created_at'=>$now]);
        $this->audit->record(['tenant_id'=>$tenantId,'shop_id'=>$shopId,'actor_type'=>'USER','actor_user_id'=>$actorUserId,'actor_membership_id'=>$membershipId,'action_code'=>'branding.asset_replaced','category'=>'TENANT','resource_type'=>'shop_brand_asset','resource_public_id'=>$publicId,'change_metadata'=>['asset_type'=>$assetType]]);
        $this->database->transComplete();

        return ['public_id'=>$publicId,'asset_type'=>$assetType];
    }
}
