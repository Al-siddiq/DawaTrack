<?php

declare(strict_types=1);

namespace App\Modules\BrandingDomain\Application;

use CodeIgniter\Database\BaseConnection;

final class BrandResolver
{
    public function __construct(private BaseConnection $database)
    {
    }

    public function resolve(string $host): ?array
    {
        $host = strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? '');
        $domain = $this->database->table('shop_domains')->where(['hostname' => $host, 'is_active' => 1, 'verification_status' => 'VERIFIED'])->get()->getRowArray();
        if (! $domain) {
            return null;
        }

        $brand = $this->database->table('shop_branding_profiles')->where(['tenant_id' => $domain['tenant_id'], 'shop_id' => $domain['shop_id']])->get()->getRowArray();
        if (! $brand) return null;
        $assets=$this->database->table('shop_brand_assets')->select('public_id,asset_type,content_sha256')->where(['tenant_id'=>$domain['tenant_id'],'shop_id'=>$domain['shop_id'],'is_active'=>1])->get()->getResultArray(); $brand['assets']=[];
        foreach($assets as $asset) $brand['assets'][$asset['asset_type']]='/brand-assets/'.$asset['public_id'].'?v='.bin2hex($asset['content_sha256']); return $brand;
    }
}
