<?php

declare(strict_types=1);
namespace App\Modules\Sales\Application;

use App\Exceptions\ApiException;
use App\Modules\Reconciliation\Application\CashAmount;

/** Kobo arithmetic on bounded decimal strings. Never converts money to a PHP float or machine-sized total. */
final class SaleMoney
{
    public static function sum(array $amounts): string
    {
        $digits = "0";
        foreach ($amounts as $amount) {
            $digits = self::addDigits(
                $digits,
                str_replace(".", "", CashAmount::parse($amount)),
            );
        }
        return CashAmount::parse(self::decimal($digits));
    }
    public static function difference(string $left, string $right): string
    {
        $a = ltrim(str_replace(".", "", CashAmount::parse($left)), "0") ?: "0";
        $b = ltrim(str_replace(".", "", CashAmount::parse($right)), "0") ?: "0";
        $compare = strlen($a) <=> strlen($b);
        if ($compare === 0) {
            $compare = strcmp($a, $b);
        }
        $negative = $compare < 0;
        if ($negative) {
            [$a, $b] = [$b, $a];
        }
        $a = strrev($a);
        $b = strrev($b);
        $borrow = 0;
        $out = "";
        for ($i = 0; $i < strlen($a); $i++) {
            $n = (int) $a[$i] - (int) ($b[$i] ?? "0") - $borrow;
            $borrow = $n < 0 ? 1 : 0;
            $out .= (string) ($n < 0 ? $n + 10 : $n);
        }
        $value = self::decimal(strrev($out));
        return $negative && $value !== "0.00" ? "-" . $value : $value;
    }
    public static function composition(
        string $total,
        mixed $cash,
        mixed $transfer,
    ): array {
        $cash = CashAmount::parse($cash);
        $transfer = CashAmount::parse($transfer);
        $paid = self::sum([$cash, $transfer]);
        $credit = self::difference($total, $paid);
        if (str_starts_with($credit, "-")) {
            throw new ApiException(
                "PAYMENT_EXCEEDS_TOTAL",
                "Payments exceed the Sale total. No advance balance is supported.",
                422,
            );
        }
        return [
            "cash_amount" => $cash,
            "transfer_amount" => $transfer,
            "paid_amount" => $paid,
            "credit_amount" => $credit,
        ];
    }
    private static function addDigits(string $a, string $b): string
    {
        $a = strrev($a);
        $b = strrev($b);
        $carry = 0;
        $out = "";
        for ($i = 0; $i < max(strlen($a), strlen($b)); $i++) {
            $n = (int) ($a[$i] ?? "0") + (int) ($b[$i] ?? "0") + $carry;
            $out .= (string) ($n % 10);
            $carry = intdiv($n, 10);
        }
        if ($carry) {
            $out .= (string) $carry;
        }
        return strrev($out);
    }
    private static function decimal(string $digits): string
    {
        $digits = str_pad(ltrim($digits, "0") ?: "0", 3, "0", STR_PAD_LEFT);
        return substr($digits, 0, -2) . "." . substr($digits, -2);
    }
}
