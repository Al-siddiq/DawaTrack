<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure;

/** Cash API compatibility; shared repository remains scope-bound by its constructor. */
final class CashControlRepository extends ControlRepository {}
