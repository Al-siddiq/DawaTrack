<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Exceptions\ApiException;

/** Exact signed DECIMAL(19,4) count/movement arithmetic; no binary floating point. */
final class StockCountAmount
{
    public static function parse(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^(?:0|[1-9][0-9]{0,14})(?:\.[0-9]{1,4})?$/D', trim($value))) {
            throw new ApiException('VALIDATION_FAILED', 'Enter a non-negative physical quantity with at most four decimal places.', 422,
                ['actual_quantity' => ['Use an exact quantity, including zero when none is present.']]);
        }
        [$whole, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');
        return $whole . '.' . str_pad($fraction, 4, '0');
    }

    public static function signed(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $raw = self::parse($negative ? substr($value, 1) : $value);
        return $negative && $raw !== '0.0000' ? '-' . $raw : $raw;
    }

    public static function difference(string $actual, string $expected): string
    {
        $expected = self::signed($expected);
        $inverse = str_starts_with($expected, '-') ? substr($expected, 1) : '-' . $expected;
        return self::add(self::parse($actual), $inverse);
    }

    public static function add(string $left, string $right): string
    {
        $left = self::signed($left);
        $right = self::signed($right);
        $aNegative = str_starts_with($left, '-');
        $bNegative = str_starts_with($right, '-');
        $a = ltrim(str_replace(['-', '.'], '', $left), '0') ?: '0';
        $b = ltrim(str_replace(['-', '.'], '', $right), '0') ?: '0';
        $negative = $aNegative;
        if ($aNegative === $bNegative) {
            $aa = strrev($a);
            $bb = strrev($b);
            $carry = 0;
            $digits = '';
            for ($i = 0; $i < max(strlen($aa), strlen($bb)); $i++) {
                $n = (int) ($aa[$i] ?? '0') + (int) ($bb[$i] ?? '0') + $carry;
                $digits .= (string) ($n % 10);
                $carry = intdiv($n, 10);
            }
            $digits = strrev($digits . ($carry ? (string) $carry : ''));
        } else {
            $compare = (strlen($a) <=> strlen($b)) ?: strcmp($a, $b);
            if ($compare < 0) {
                [$a, $b] = [$b, $a];
                $negative = $bNegative;
            }
            $aa = strrev($a);
            $bb = strrev($b);
            $borrow = 0;
            $digits = '';
            for ($i = 0; $i < strlen($aa); $i++) {
                $n = (int) $aa[$i] - (int) ($bb[$i] ?? '0') - $borrow;
                $borrow = $n < 0 ? 1 : 0;
                $digits .= (string) ($n < 0 ? $n + 10 : $n);
            }
            $digits = strrev($digits);
        }
        $digits = ltrim($digits, '0') ?: '0';
        if (strlen($digits) > 19) {
            throw new ApiException('STOCK_COUNT_RANGE_EXCEEDED', 'The exact Stock quantity or variance exceeds the supported range.', 409);
        }
        $digits = str_pad($digits, 5, '0', STR_PAD_LEFT);
        $value = substr($digits, 0, -4) . '.' . substr($digits, -4);
        return $negative && $value !== '0.0000' ? '-' . $value : $value;
    }
}
