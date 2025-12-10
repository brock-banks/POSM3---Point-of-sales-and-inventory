<?php
require_once __DIR__ . '/../src/i18n.php';

function translations(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $lang = app_language();
    $file = __DIR__ . '/lang_' . $lang . '.php';

    if (!file_exists($file)) {
        $file = __DIR__ . '/lang_en.php';
    }

    $cache = require $file;
    return $cache;
}

/**
 * Translate key to current language.
 * Usage: __('nav.dashboard')
 */
function __(string $key, ?string $default = null): string {
    $t = translations();
    if (isset($t[$key])) return $t[$key];
    // fallback: use default or key
    return $default ?? $key;
}