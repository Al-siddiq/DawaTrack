<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Support\Uuid;
use CodeIgniter\Database\Seeder;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class PhaseOneSeeder extends Seeder
{
    private const LOCK_NAME = 'dawatrack:phase-one-bootstrap';

    /** @var list<array{code: string, name: string, max_active_staff: int, monthly_price_amount: string|null}> */
    private const PLANS = 
    [
        [
            'code' => 'TRIAL',
            'name' => 'Trial',
            'max_active_staff' => 5,
            'monthly_price_amount' => '0.00',
        ],
        [
            'code' => 'STARTER',
            'name' => 'Starter',
            'max_active_staff' => 2,
            'monthly_price_amount' => '5000.00',
        ],
        [
            'code' => 'GROWTH',
            'name' => 'Growth',
            'max_active_staff' => 5,
            'monthly_price_amount' => '10000.00',
        ],
    ];

    public function run(): void
    {
        $lock = $this->db->query('SELECT GET_LOCK(?, 30) AS acquired', [self::LOCK_NAME])->getRowArray();
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the Phase 1 bootstrap lock.');
        }

        try {
            $this->db->transException(true)->transStart();
            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

            foreach (self::PLANS as $plan) {
                $existing = $this->db->table('subscription_plans')->where('code', $plan['code'])->get()->getRowArray();
                if ($existing === null) {
                    $this->db->table('subscription_plans')->insert($plan + [
                        'public_id' => Uuid::v4(),
                        'currency_code' => 'NGN',
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    continue;
                }

                $actualPrice = $existing['monthly_price_amount'] === null ? null : number_format((float) $existing['monthly_price_amount'], 2, '.', '');
                if (
                    $existing['name'] !== $plan['name']
                    || (int) $existing['max_active_staff'] !== $plan['max_active_staff']
                    || $actualPrice !== $plan['monthly_price_amount']
                    || $existing['currency_code'] !== 'NGN'
                    || (int) $existing['is_active'] !== 1
                ) {
                    throw new RuntimeException("Phase 1 bootstrap drift detected for subscription plan {$plan['code']}.");
                }
            }

            $activeGenerations = $this->db->query("SELECT * FROM sync_generations WHERE status = 'ACTIVE' FOR UPDATE")->getResultArray();
            if (count($activeGenerations) > 1) {
                throw new RuntimeException('Phase 1 bootstrap drift detected: more than one active Sync Generation exists.');
            }
            if ($activeGenerations === []) {
                $this->db->table('sync_generations')->insert([
                    'generation_uuid' => Uuid::v4(),
                    'status' => 'ACTIVE',
                    'reason' => 'Initial platform generation',
                    'activated_at' => $now,
                    'created_at' => $now,
                ]);
            } elseif ($activeGenerations[0]['reason'] !== 'Initial platform generation') {
                throw new RuntimeException('Phase 1 bootstrap drift detected for the active Sync Generation.');
            }

            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }
    }
}
