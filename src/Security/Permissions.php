<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Canonical permission codes used across the application.
 *
 * Keeping codes in one place avoids typos in controllers, role seeding, and
 * admin role-management screens.
 */
final class Permissions
{
    public const ADMIN_ACCESS = 'admin.access';
    public const USERS_VIEW = 'users.view';
    public const USERS_EDIT = 'users.edit';
    public const USERS_DELETE = 'users.delete';
    public const USERS_ASSIGN_ROLES = 'users.assign_roles';
    public const RESERVATIONS_VIEW_ALL = 'reservations.view_all';
    public const RESERVATIONS_MANAGE_ALL = 'reservations.manage_all';
    public const SETTINGS_MANAGE = 'settings.manage';
    public const MESSAGES_VIEW_PRIVATE = 'messages.view_private';
    public const BRANDING_MANAGE = 'branding.manage';
    public const UPDATES_MANAGE = 'updates.manage';
    public const ROLES_MANAGE = 'roles.manage';

    /**
     * Ordered list used for validation and role-management screens.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ADMIN_ACCESS,
            self::USERS_VIEW,
            self::USERS_EDIT,
            self::USERS_DELETE,
            self::USERS_ASSIGN_ROLES,
            self::RESERVATIONS_VIEW_ALL,
            self::RESERVATIONS_MANAGE_ALL,
            self::SETTINGS_MANAGE,
            self::MESSAGES_VIEW_PRIVATE,
            self::BRANDING_MANAGE,
            self::UPDATES_MANAGE,
            self::ROLES_MANAGE,
        ];
    }
}
