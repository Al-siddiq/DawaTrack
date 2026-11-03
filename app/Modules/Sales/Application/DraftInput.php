<?php

declare(strict_types=1);
namespace App\Modules\Sales\Application;
use App\Exceptions\ApiException;
use App\Support\Uuid;

final class DraftInput
{
    public static function only(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Unsupported or protected fields were submitted.",
                422,
            );
        }
    }
    public static function text(
        mixed $value,
        string $field,
        int $max,
        bool $required = false,
    ): ?string {
        if ($value === null && !$required) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Use text for " . $field . ".",
                422,
                [$field => ["Text is required."]],
            );
        }
        $value = trim($value);
        if (mb_strlen($value) > $max || ($required && $value === "")) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Check " . $field . ".",
                422,
                [
                    $field => [
                        "Enter " .
                        ($required ? "nonempty " : "") .
                        "text within " .
                        $max .
                        " characters.",
                    ],
                ],
            );
        }
        return $value === "" ? null : $value;
    }
    public static function uuid(
        mixed $value,
        string $field = "public_id",
    ): string {
        if (!is_string($value) || !Uuid::isV4(strtolower($value))) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Use a permanent UUIDv4.",
                422,
                [$field => ["A UUIDv4 is required."]],
            );
        }
        return strtolower($value);
    }
    public static function phone(mixed $value): ?string
    {
        $text = self::text($value, "phone", 32);
        if ($text === null) {
            return null;
        }
        if (!preg_match('/^\+?[0-9() .-]+$/D', $text)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Enter a valid phone number.",
                422,
                ["phone" => ["Use digits and common separators."]],
            );
        }
        $phone = preg_replace("/[() .-]/", "", $text);
        if (!preg_match('/^\+?[0-9]{7,15}$/D', $phone)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Enter 7 to 15 phone digits.",
                422,
                ["phone" => ["Enter 7 to 15 digits."]],
            );
        }
        return $phone;
    }
    public static function occurred(mixed $value): string
    {
        $text = self::text($value, "occurred_at", 26, true);
        $date = \DateTimeImmutable::createFromFormat(
            "!Y-m-d H:i:s.u",
            $text,
            new \DateTimeZone("UTC"),
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            !$date ||
            (int) $date->format("Y") < 1000 ||
            ($errors !== false &&
                ($errors["warning_count"] || $errors["error_count"])) ||
            $date->format("Y-m-d H:i:s.u") !== $text
        ) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Enter a valid UTC occurrence time.",
                422,
                ["occurred_at" => ["Use YYYY-MM-DD HH:mm:ss.ffffff UTC."]],
            );
        }
        return $text;
    }
    public static function dueDate(mixed $value): ?string
    {
        $text = self::text($value, "due_date", 10);
        if ($text === null) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat("!Y-m-d", $text);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            !$date ||
            (int) $date->format("Y") < 1000 ||
            ($errors !== false &&
                ($errors["warning_count"] || $errors["error_count"])) ||
            $date->format("Y-m-d") !== $text
        ) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Enter a valid due date.",
                422,
                ["due_date" => ["Use a valid YYYY-MM-DD calendar date."]],
            );
        }
        return $text;
    }
    public static function offset(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < -840 || $value > 840) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Use a valid local UTC offset.",
                422,
            );
        }
        return $value;
    }
    public static function version(array $row, mixed $expected): void
    {
        if (!is_string($expected) || $expected !== $row["updated_at"]) {
            throw new ApiException(
                "DRAFT_STALE",
                "This record changed. Reload its current state before editing.",
                409,
            );
        }
    }
    public static function nextTime(string $old): string
    {
        $now = \App\Support\UtcTimestamp::now();
        return $now > $old
            ? $now
            : (new \DateTimeImmutable($old, new \DateTimeZone("UTC")))
                ->modify("+1 microsecond")
                ->format("Y-m-d H:i:s.u");
    }
}
