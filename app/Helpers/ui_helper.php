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
