<?php
// Shared environment helper: reads System Environment Variables first,
// falls back to a local .env file for development.
function getEnvVar($key, $default = null) {
    $value = getenv($key);
    if ($value !== false) return $value;

    static $env = null;
    if ($env === null) {
        $path = __DIR__ . '/../.env';
        $env = file_exists($path) ? parse_ini_file($path) : [];
    }
    return $env[$key] ?? $default;
}

/** Format an amount in Zambian Kwacha (ZMW). */
function money($amount) {
    return 'K' . number_format((float) ($amount ?? 0), 2);
}