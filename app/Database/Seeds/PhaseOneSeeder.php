<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Support\Uuid;
use CodeIgniter\Database\Seeder;

final class PhaseOneSeeder extends Seeder
{
    public function run(): void
    {
        $now = gmdate('Y-m-d H:i:s.u');
        $this->db->transException(true)->transStart();

        foreach ([
            ['code' => 'TRIAL', 'name' => 'Trial', 'max_active_staff' => 5, 'monthly_price_amount' => '0.00'],
            ['code' => 'STARTER', 'name' => 'Starter', 'max_active_staff' => 2, 'monthly_price_amount' => null],
            ['code' => 'GROWTH', 'name' => 'Growth', 'max_active_staff' => 5, 'monthly_price_amount' => null],
        ] as $plan) {
            if (! $this->db->table('subscription_plans')->where('code', $plan['code'])->countAllResults()) {
                $this->db->table('subscription_plans')->insert($plan + [
                    'public_id' => Uuid::v4(), 'currency_code' => 'NGN', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        if (! $this->db->table('sync_generations')->where('status', 'ACTIVE')->countAllResults()) {
            $this->db->table('sync_generations')->insert([
                'generation_uuid' => Uuid::v4(), 'status' => 'ACTIVE', 'reason' => 'Initial platform generation', 'activated_at' => $now, 'created_at' => $now,
            ]);
        }

        $this->db->transComplete();
    }
}
