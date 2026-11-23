<?php

// =============================================================================
// Prince Byron — Root loader for cPanel / shared hosting deployment
// =============================================================================
// This file sits at public_html/ (or domain document root) on the host.
// It delegates all web requests to the Laravel front controller in core/public/.
//
// Security: this file must NOT expose core/, .env, vendor/, logs, or storage/.
// =============================================================================

define('LARAVEL_START', microtime(true));

// Auto-redirect to installer if not installed yet (first-time upload)
if (!file_exists(__DIR__ . '/core/storage/installed') && !file_exists(__DIR__ . '/core/.env')) {
    if (file_exists(__DIR__ . '/install/index.php')) {
        header("Location: install/");
        exit;
    }
}

// Deny direct access attempts to private directories (Defense-in-depth)
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$normalized = '/' . ltrim(str_replace('\\', '/', rawurldecode($path)), '/');

$blocked = ['/core', '/vendor', '/storage', '/.env', '/.git'];
foreach ($blocked as $prefix) {
    if ($normalized === $prefix || str_starts_with($normalized, $prefix . '/') || str_starts_with($normalized, $prefix)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Block sensitive file extensions
$blockedExtensions = ['.env', '.sql', '.log', '.lock'];
foreach ($blockedExtensions as $ext) {
    if (str_ends_with(strtolower($normalized), $ext)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Hand off to Laravel's public front controller.
require __DIR__ . '/core/public/index.php';
