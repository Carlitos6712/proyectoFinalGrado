<?php
/**
 * CSRF token helpers.
 *
 * Simple per-action tokens stored in $_SESSION['csrf_tokens'].
 * Each action gets one token; it survives across requests (not one-time),
 * which lets the confirmation page re-render without losing validity.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 */

/**
 * Generate (or reuse) a CSRF token for the given action.
 *
 * @param  string $action Logical action name (e.g. 'eliminar_producto').
 * @return string Hex token.
 */
function generateCsrfToken(string $action): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_tokens'][$action])) {
        $_SESSION['csrf_tokens'][$action] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_tokens'][$action];
}

/**
 * Validate a CSRF token for the given action.
 *
 * @param  string $action Logical action name.
 * @param  string $token  Token submitted by the client.
 * @return bool True when valid, false otherwise.
 */
function validateCsrfToken(string $action, string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $stored = $_SESSION['csrf_tokens'][$action] ?? '';

    return $stored !== '' && hash_equals($stored, $token);
}
