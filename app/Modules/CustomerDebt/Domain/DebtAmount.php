<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Domain;

use App\Exceptions\ApiException;

/** Unsigned DECIMAL(38,2) aggregates; input payments keep their separate DECIMAL(19,2) bound. */
final class DebtAmount
{
    private static function digits(string $amount): string
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,35})(?:\.([0-9]{1,2}))?$/D', $amount, $parts)) {
            throw new ApiException('DEBT_EVIDENCE_INVALID', 'These balances need checking. Ask the shop owner for help.', 409);
        }
        return ltrim($parts[1] . str_pad($parts[2] ?? '', 2, '0'), '0') ?: '0';
    }

    private static function decimal(string $digits): string
    {
        $digits = str_pad(ltrim($digits, '0') ?: '0', 3, '0', STR_PAD_LEFT);
        $value = substr($digits, 0, -2) . '.' . substr($digits, -2);
        self::digits($value);
        return $value;
    }

    public static function compare(string $left, string $right): int
    {
        $a = self::digits($left);
        $b = self::digits($right);
        return (strlen($a) <=> strlen($b)) ?: strcmp($a, $b);
    }

    public static function sum(array $amounts): string
    {
        $total = '0';
        foreach ($amounts as $amount) {
            $a = strrev($total);
            $b = strrev(self::digits($amount));
            $carry = 0;
            $next = '';
            for ($i = 0; $i < max(strlen($a), strlen($b)); $i++) {
                $n = (int) ($a[$i] ?? '0') + (int) ($b[$i] ?? '0') + $carry;
                $next .= (string) ($n % 10);
                $carry = intdiv($n, 10);
            }
            $total = strrev($next . ($carry ? (string) $carry : ''));
        }
        return self::decimal($total);
    }

    public static function subtract(string $left, string $right): string
    {
        if (self::compare($left, $right) < 0) {
            throw new ApiException('DEBT_EVIDENCE_INVALID', 'The payment would leave an invalid balance.', 409);
        }
        $a = strrev(self::digits($left));
        $b = strrev(self::digits($right));
        $borrow = 0;
        $out = '';
        for ($i = 0; $i < strlen($a); $i++) {
            $n = (int) $a[$i] - (int) ($b[$i] ?? '0') - $borrow;
            $borrow = $n < 0 ? 1 : 0;
            $out .= (string) ($n < 0 ? $n + 10 : $n);
        }
        return self::decimal(strrev($out));
    }
}
