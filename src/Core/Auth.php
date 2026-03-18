<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-based authentication helper.
 *
 * The class centralizes login/logout and both role- and permission-based
 * authorization checks so controllers do not have to duplicate
 * security-sensitive session handling.
 */
final class Auth
{
    private static ?array $resolvedUser = null;
    private static bool $userLoaded = false;

    /**
     * Persist the authenticated user ID and rotate the session identifier to
     * prevent session fixation after login.
     */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        self::$resolvedUser = $user;
        self::$userLoaded = true;
    }

    public static function logout(): void
    {
        // Rotating again on logout reduces the chance of session reuse.
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        self::$resolvedUser = null;
        self::$userLoaded = false;
    }

    /**
     * Resolve the current user from session state.
     *
     * Returns null when there is no authenticated user or the account no longer
     * exists in the database.
     */
    public static function user(): ?array
    {
        if (self::$userLoaded) {
            return self::$resolvedUser;
        }

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        self::$resolvedUser = (new User())->findById((int) $_SESSION['user_id']);
        self::$userLoaded = true;

        return self::$resolvedUser;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function hasPermission(string|array $permissions): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        if ((int) ($user['is_super_admin'] ?? 0) === 1) {
            return true;
        }

        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $granted = self::permissionCodes();

        foreach ($permissions as $permission) {
            if (in_array($permission, $granted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function permissionCodes(): array
    {
        $user = self::user();
        if ($user === null) {
            return [];
        }

        $codes = $user['permission_codes'] ?? [];
        if (is_array($codes)) {
            return $codes;
        }

        $csv = trim((string) ($user['permission_codes_csv'] ?? ''));
        if ($csv === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    public static function requireUser(): void
    {
        if (!self::check()) {
            throw new HttpException('Unauthorized', 403);
        }
    }

    public static function requirePermission(string|array $permissions): void
    {
        if (!self::hasPermission($permissions)) {
            throw new HttpException('Forbidden', 403);
        }
    }
}
