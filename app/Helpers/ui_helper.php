<?php

declare(strict_types=1);

use App\Support\UiIconCatalogue;

if (! function_exists('dt_ui_icon')) {
    /**
     * Render a trusted, same-origin icon for server views. Load with helper('ui').
     * Decorative by default: the surrounding link/button must supply visible text.
     * Never pass raw SVG markup or user-controlled asset URLs.
     */
    function dt_ui_icon(string $name, string $size = 'action', string $label = ''): string
    {
        if (! isset(UiIconCatalogue::SYMBOLS[$name])) {
            throw new InvalidArgumentException("Unknown UI icon: {$name}");
        }
        if (! in_array($size, ['small', 'action', 'navigation'], true)) {
            throw new InvalidArgumentException("Unknown UI icon size: {$size}");
        }
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = dt_url(UiIconCatalogue::SPRITE) . '#' . UiIconCatalogue::SYMBOLS[$name];
        $accessible = $label === ''
            ? ' aria-hidden="true"'
            : ' role="img" aria-label="' . $escape($label) . '"';

        return '<svg class="ui-icon ui-icon--' . $escape($size) . '" viewBox="0 0 16 16" fill="currentColor" focusable="false"'
            . $accessible . '><use href="' . $escape($url) . '"></use></svg>';
    }
}

if (! function_exists('dt_ui_brand_palette')) {
    /** Presentation-only colours: validate input and darken within its hue for readable actions. */
    function dt_ui_brand_palette(string $primary, string $accent = '#F0A202'): array
    {
        $valid = static fn (string $colour, string $fallback): string =>
            preg_match('/^#[0-9a-f]{6}$/i', $colour) ? strtoupper($colour) : $fallback;
        $mix = static function (string $colour, int $whitePercent): string {
            $channels = [];
            foreach ([1, 3, 5] as $offset) {
                $channels[] = (int) round(hexdec(substr($colour, $offset, 2)) * (100 - $whitePercent) / 100 + 255 * $whitePercent / 100);
            }
            return sprintf('#%02X%02X%02X', ...$channels);
        };
        $darken = static function (string $colour, int $percent): string {
            $channels = [];
            foreach ([1, 3, 5] as $offset) {
                $channels[] = (int) round(hexdec(substr($colour, $offset, 2)) * (100 - $percent) / 100);
            }
            return sprintf('#%02X%02X%02X', ...$channels);
        };
        $whiteContrast = static function (string $colour): float {
            $channels = [];
            foreach ([1, 3, 5] as $offset) {
                $value = hexdec(substr($colour, $offset, 2)) / 255;
                $channels[] = $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            }
            return 1.05 / (0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2] + 0.05);
        };
        $raw = $valid($primary, '#155D46');
        $action = $raw;
        // 5.5:1 against white also leaves room for the pale selected surface.
        for ($percent = 1; $whiteContrast($action) < 5.5 && $percent <= 100; $percent++) {
            $action = $darken($raw, $percent);
        }
        return [
            'brand' => $raw,
            'primary' => $action,
            'primary-hover' => $darken($action, 12),
            'selected' => $mix($action, 92),
            'hover-surface' => $mix($action, 96),
            'focus' => $action,
            'accent' => $valid($accent, '#F0A202'),
        ];
    }
}

if (! function_exists('dt_ui_brand_primary')) {
    /** Kept for callers needing only the readable action colour. */
    function dt_ui_brand_primary(string $candidate): string
    {
        return dt_ui_brand_palette($candidate)['primary'];
    }
}
