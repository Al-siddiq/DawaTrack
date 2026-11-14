<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Application;

use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class ExpenseService
{
    private readonly MovementRecordingService $recording;

    public function __construct(BaseConnection $database, CommercialAccess $access, AuditService $audit, ChangeSequenceService $changes, RequestContext $request)
    {
        $this->recording = new MovementRecordingService($database, $access, $audit, $changes, $request);
    }

    public function preview(ShopContext $context, array $input): array
    {
        return $this->recording->previewExpense($context, $input);
    }

    public function post(ShopContext $context, array $input): string
    {
        return $this->recording->postExpense($context, $input);
    }
}
