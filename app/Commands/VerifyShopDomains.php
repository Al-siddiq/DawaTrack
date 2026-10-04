<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\BrandingDomain\Application\DomainService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Config\Services;
use DomainException;
use Psr\Log\LoggerInterface;

final class VerifyShopDomains extends BaseCommand
{
    protected $group = 'DawaTrack';
    protected $name = 'domains:verify';
    protected $description = 'Verify pending custom-domain DNS TXT challenges using the application service.';

    public function __construct(LoggerInterface $logger, Commands $commands)
    {
        parent::__construct($logger, $commands);
    }

    public function run(array $params): void
    {
        $correlationId = Services::requestContext()->initialize(null);
        $database = db_connect();
        $service = new DomainService($database, Services::auditService());
        $domains = $database->table('shop_domains')->where([
            'domain_type' => 'CUSTOM',
            'verification_status' => 'PENDING',
        ])->get()->getResultArray();

        log_message('info', 'job.domain_verification_started {request_id}', ['request_id' => $correlationId]);
        foreach ($domains as $domain) {
            try {
                $matched = $service->verify($domain);
                CLI::write($domain['hostname'] . ($matched ? ': verified' : ': pending'), $matched ? 'green' : 'yellow');
            } catch (DomainException $exception) {
                CLI::error($domain['hostname'] . ': ' . $exception->getMessage());
            }
        }
        log_message('info', 'job.domain_verification_completed {request_id}', ['request_id' => $correlationId]);
        CLI::write("Correlation: {$correlationId}");
    }
}
