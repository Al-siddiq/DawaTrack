<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

enum AuditAuthority: string
{
    case TENANT = 'TENANT';
    case PLATFORM = 'PLATFORM';
    case IDENTITY = 'IDENTITY';
    case SYSTEM = 'SYSTEM';
}
