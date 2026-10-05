<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

enum ChangeKind: string
{
    case UPSERT = 'UPSERT';
    case DEACTIVATE = 'DEACTIVATE';
}
