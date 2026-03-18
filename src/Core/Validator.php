<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small validation helper for cross-cutting rules reused across forms.
 */
final class Validator
{
    public static function requireCsrf(Request $request): void
    {
        // Controllers call this explicitly so POST endpoints cannot be missed
        // accidentally during future feature work.
        if (!Csrf::validate((string) $request->input('_csrf'))) {
            throw new HttpException('Invalid CSRF token', 419);
        }
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function apartment(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9\-\/ ]{0,19}$/', $value) === 1;
    }
}
