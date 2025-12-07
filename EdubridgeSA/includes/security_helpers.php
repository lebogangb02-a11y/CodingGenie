<?php

/**
 * Security helper functions for EduBridgeSA
 * - h(): safe HTML escaping wrapper
 * - require_csrf(): server-side CSRF validation for POST handlers
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * Escape for HTML output
 */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate CSRF token in POST requests. Exits with 403 on failure.
 */
function require_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // Only enforce for POST requests
    }

    if (empty($_POST[CSRF_TOKEN_NAME]) || !is_string($_POST[CSRF_TOKEN_NAME])) {
        http_response_code(403);
        error_log('CSRF token missing or invalid type');
        exit('CSRF validation failed');
    }

    $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!hash_equals((string)$sessionToken, (string)$_POST[CSRF_TOKEN_NAME])) {
        http_response_code(403);
        error_log('CSRF token mismatch');
        exit('CSRF validation failed');
    }

    return true;
}

/**
 * Simple integer validation helper
 */
function v_int($val)
{
    return filter_var($val, FILTER_VALIDATE_INT);
}
