<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Subscription\Application\SubscriptionService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Config\Services;
use Psr\Log\LoggerInterface;

final class RecordSubscriptionExpiries extends BaseCommand
{
    protected $group = 'DawaTrack';
    protected $name = 'subscriptions:record-expiries';
    protected $description = 'Append repeat-safe evidence for time-derived entitlement expiries.';
    protected $usage = 'subscriptions:record-expiries <platform-admin-user-id>';

    public function __construct(LoggerInterface $logger, Commands $commands)
    {
        parent::__construct($logger, $commands);
    }

    public function run(array $params): void
    {
        $correlationId = Services::requestContext()->initialize(null);
        $actor = (int) ($params[0] ?? 0);
        $isPlatformAdmin = $actor > 0 && db_connect()->table('auth_groups_users')->where([
            'user_id' => $actor,
            'group' => 'platform_admin',
        ])->countAllResults() === 1;
        if (! $isPlatformAdmin) {
            CLI::error('Provide a valid controlled Platform Administrator user ID.');

            return;
        }

        log_message('info', 'job.subscription_expiries_started {request_id}', ['request_id' => $correlationId]);
        $count = (new SubscriptionService(db_connect(), Services::auditService()))->recordExpiries($actor);
        log_message('info', 'job.subscription_expiries_completed {request_id} {count}', [
            'request_id' => $correlationId,
            'count' => $count,
        ]);
        CLI::write("Recorded {$count} expiry event(s). Correlation: {$correlationId}", 'green');
    }
}
