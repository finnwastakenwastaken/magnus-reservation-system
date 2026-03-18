-- Baseline schema used by the installer and as a human-readable reference.
-- The migration runner uses versioned SQL files under database/migrations/.

CREATE TABLE IF NOT EXISTS permissions (
    -- Permission catalog used by the role-based authorization system.
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    -- A single primary role is assigned to each user.
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
    -- Maps granular permissions to a role. The protected super-admin role
    -- bypasses normal permission checks even without explicit mappings.
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    -- Resident and administrator accounts.
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    apartment_number VARCHAR(20) NOT NULL,
    phone_number VARCHAR(30) DEFAULT NULL,
    contact_notes VARCHAR(255) DEFAULT NULL,
    show_phone_to_users TINYINT(1) NOT NULL DEFAULT 0,
    show_contact_notes_to_users TINYINT(1) NOT NULL DEFAULT 0,
    password_hash VARCHAR(255) NOT NULL,
    profile_picture_path VARCHAR(255) DEFAULT NULL,
    role_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    activation_code_hash VARCHAR(255) DEFAULT NULL,
    activation_code_created_at DATETIME DEFAULT NULL,
    pending_email VARCHAR(190) DEFAULT NULL,
    pending_email_token_hash VARCHAR(255) DEFAULT NULL,
    pending_email_requested_at DATETIME DEFAULT NULL,
    pending_email_expires_at DATETIME DEFAULT NULL,
    activated_at DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    anonymized_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_pending_email (pending_email),
    KEY idx_users_active (is_active),
    KEY idx_users_role_id (role_id),
    KEY idx_users_deleted (deleted_at),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
    -- Shared living room reservations. Indexes focus on overlap and per-user queries.
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    cancelled_at DATETIME DEFAULT NULL,
    cancelled_by_user_id INT UNSIGNED DEFAULT NULL,
    last_modified_by_user_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_reservations_modified_by FOREIGN KEY (last_modified_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_reservations_window (start_datetime, end_datetime, status),
    KEY idx_reservations_user_status (user_id, status, start_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    -- Internal messages between active residents.
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_user_id INT UNSIGNED NOT NULL,
    recipient_user_id INT UNSIGNED NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    read_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_messages_recipient_created (recipient_user_id, created_at),
    KEY idx_messages_sender_created (sender_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    -- Key/value store for admin-configurable booking rules and app defaults.
    `key` VARCHAR(100) PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    -- Reserved for future token-based reset flows and admin-created resets.
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    reset_token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_by_user_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_password_resets_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_password_resets_user (user_id),
    KEY idx_password_resets_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    -- Stores per-action counters for brute-force and abuse protection.
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_key VARCHAR(100) NOT NULL,
    bucket_key VARCHAR(190) NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    window_started_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_rate_limits_action_bucket (action_key, bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    -- Important security and admin events.
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    event_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id VARCHAR(100) NOT NULL,
    details_json JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_audit_event (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    -- In-app notifications for resident-visible account and reservation events.
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_notifications_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migrations (
    -- Tracks ordered SQL migrations applied by the updater.
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(190) NOT NULL,
    applied_at DATETIME NOT NULL,
    UNIQUE KEY uq_migration_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`, updated_at) VALUES
('booking_start_hour', '9', NOW()),
('booking_end_hour', '22', NOW()),
('max_hours_per_week', '6', NOW()),
('max_hours_per_month', '12', NOW()),
('timezone', 'Europe/Amsterdam', NOW()),
('site_logo_path', '', NOW()),
('retention_unactivated_days', '60', NOW()),
('retention_password_reset_days', '30', NOW()),
('retention_rate_limit_days', '30', NOW()),
('retention_update_backup_days', '30', NOW())
ON DUPLICATE KEY UPDATE
`value` = VALUES(`value`),
updated_at = VALUES(updated_at);

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
