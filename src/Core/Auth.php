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

    public static function requireUser(): void
    {
        if (!self::check()) {
            throw new HttpException('Unauthorized', 403);
        }
    }

    public static function requireAdmin(): void
    {
        // Role checks are enforced server-side on every admin route to prevent
        // privilege escalation through crafted requests.
        $user = self::user();
        if (!$user || $user['role'] !== 'admin') {
            throw new HttpException('Forbidden', 403);
        }
    }
}
