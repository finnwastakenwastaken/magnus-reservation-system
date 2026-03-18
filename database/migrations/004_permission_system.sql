-- Replace the old enum-based role flag with a database-backed role and
-- permission system while preserving existing user assignments.

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (code, name, description, created_at) VALUES
('admin.access', 'Access staff dashboard', 'Open the shared staff/admin area and overview.', NOW()),
('users.view', 'View users', 'View user lists and resident account information.', NOW()),
('users.edit', 'Edit users', 'Update apartment assignment and other admin-managed user fields.', NOW()),
('users.delete', 'Delete users', 'Anonymize and delete resident accounts.', NOW()),
('users.assign_roles', 'Assign roles', 'Assign a primary role to a user.', NOW()),
('reservations.view_all', 'View all reservations', 'View all reservation records in the staff area.', NOW()),
('reservations.manage_all', 'Manage all reservations', 'Edit or cancel any reservation.', NOW()),
('settings.manage', 'Manage reservation settings', 'Update booking hours and reservation limits.', NOW()),
('messages.view_private', 'Review private messages', 'Access private message oversight for operational reasons.', NOW()),
('branding.manage', 'Manage branding', 'Upload or reset the site logo.', NOW()),
('updates.manage', 'Manage updates', 'Check, install, and roll back in-app updates.', NOW()),
('roles.manage', 'Manage roles and permissions', 'Create, edit, and delete custom roles and permission mappings.', NOW())
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description);

INSERT INTO roles (slug, name, description, is_system, is_super_admin, created_at, updated_at) VALUES
('user', 'Resident', 'Default resident account with normal self-service access.', 1, 0, NOW(), NOW()),
('manager', 'Manager', 'Operational staff role for user, reservation, and message oversight.', 1, 0, NOW(), NOW()),
('admin', 'Administrator', 'Protected super-admin role with unrestricted staff access.', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
updated_at = VALUES(updated_at);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.code IN (
    'admin.access',
    'users.view',
    'reservations.view_all',
    'reservations.manage_all',
    'messages.view_private'
)
WHERE r.slug = 'manager'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.code IN (
    'admin.access',
    'users.view',
    'users.edit',
    'users.delete',
    'users.assign_roles',
    'reservations.view_all',
    'reservations.manage_all',
    'settings.manage',
    'messages.view_private',
    'branding.manage',
    'updates.manage',
    'roles.manage'
)
WHERE r.slug = 'admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role_id INT UNSIGNED NULL AFTER profile_picture_path;

UPDATE users u
INNER JOIN roles r ON r.slug = CASE
    WHEN u.role = 'admin' THEN 'admin'
    WHEN u.role = 'manager' THEN 'manager'
    ELSE 'user'
END
SET u.role_id = r.id
WHERE u.role_id IS NULL;

ALTER TABLE users
    MODIFY COLUMN role_id INT UNSIGNED NOT NULL,
    ADD KEY IF NOT EXISTS idx_users_role_id (role_id),
    ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT;

ALTER TABLE users
    DROP COLUMN IF EXISTS role;
