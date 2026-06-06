<?php

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

if (!function_exists('cz_asset_version')) {
    function cz_asset_version(): string {
        return defined('CZ_ASSET_VERSION') ? CZ_ASSET_VERSION : (string)time();
    }
}

if (!function_exists('cz_asset')) {
    function cz_asset(string $path): string {
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'v=' . rawurlencode(cz_asset_version());
    }
}
