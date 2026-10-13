<?php

declare(strict_types=1);

/**
 * StudentHub CSRF Protection Bridge
 * Practical 11: Reuses session-based CSRF functions from api/auth.php
 */

require_once __DIR__ . '/../api/auth.php';

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return studenthub_get_csrf_token();
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool
    {
        return studenthub_verify_csrf_token($token);
    }
}
