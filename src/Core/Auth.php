<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-based authentication helper.
 *
 * The class centralizes login/logout and authorization checks so controllers do
 * not have to duplicate security-sensitive session handling.
 */
final class Auth
{
    /**
     * Persist the authenticated user ID and rotate the session identifier to
     * prevent session fixation after login.
     */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
    }

    public static function logout(): void
    {
        // Rotating again on logout reduces the chance of session reuse.
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    /**
     * Resolve the current user from session state.
     *
     * Returns null when there is no authenticated user or the account no longer
     * exists in the database.
     */
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        return (new User())->findById((int) $_SESSION['user_id']);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return self::hasRole('admin');
    }

    public static function hasRole(string|array $roles): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($user['role'], $roles, true);
    }

    public static function requireUser(): void
    {
        if (!self::check()) {
            throw new HttpException('Unauthorized', 403);
        }
    }

    public static function requireAdmin(): void
    {
        self::requireRoles('admin');
    }

    /**
     * Enforce one or more allowed roles server-side.
     *
     * The UI may hide links, but these checks are the authoritative guard
     * against privilege escalation through crafted requests.
     */
    public static function requireRoles(string|array $roles): void
    {
        if (!self::hasRole($roles)) {
            throw new HttpException('Forbidden', 403);
        }
    }
}
