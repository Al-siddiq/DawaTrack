<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

enum AuditCategory: string
{
    case AUTHORIZATION = 'AUTHORIZATION';
    case TENANT = 'TENANT';
    case SUBSCRIPTION = 'SUBSCRIPTION';
    case INVENTORY = 'INVENTORY';
    case SALE = 'SALE';
    case DEBT = 'DEBT';
    case FINANCIAL = 'FINANCIAL';
    case RECONCILIATION = 'RECONCILIATION';
    case SYNC = 'SYNC';
    case SUPPORT = 'SUPPORT';
    case SECURITY = 'SECURITY';
}
