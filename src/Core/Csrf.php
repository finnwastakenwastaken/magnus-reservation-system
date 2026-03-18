<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token generator/validator for state-changing forms.
 *
 * Tokens are stored in session and must be validated on every POST that mutates
 * data or changes authentication state.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
    }
}
