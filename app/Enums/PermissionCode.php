<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionCode: string
{
    case InventoryView = 'inventory.view';
    case InventoryAdjust = 'inventory.adjust';
    case ProductManage = 'product.manage';
    case InventoryIntake = 'inventory.intake';
    case InventoryReverse = 'inventory.reverse';
    case CustomerView = 'customer.view';
    case CustomerManage = 'customer.manage';
    case CustomerPrivate = 'customer.private';
    case SaleView = 'sale.view';
    case SalePriceOverride = 'sale.price_override';
    case SaleProfitView = 'sale.profit.view';
    case SaleRecord = 'sale.record';
    case SaleReturn = 'sale.return';
    case SaleReverse = 'sale.reverse';
    case DebtView = 'debt.view';
    case DebtPaymentAllocate = 'debt.payment.allocate';
    case DebtAdjust = 'debt.adjust';
    case DebtPaymentReverse = 'debt.payment.reverse';
    case DebtManage = 'debt.manage';
    case DebtPaymentRecord = 'debt.payment.record';
    case FinancialView = 'financial.view';
    case FinancialRecord = 'financial.record';
    case FinancialCorrect = 'financial.correct';
    case FinancialReverse = 'financial.reverse';
    case ReconciliationView = 'reconciliation.view';
    case ReconciliationCount = 'reconciliation.count';
    case ReconciliationReview = 'reconciliation.review';
    case ReconciliationFinalize = 'reconciliation.finalize';
    case ReconciliationAdjust = 'reconciliation.adjust';
    case ReportView = 'report.view';
    case StaffManage = 'staff.manage';

    public static function accepts(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }
}
