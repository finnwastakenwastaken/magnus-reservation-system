-- Add manager role support, branding/profile image fields, reservation
-- modification tracking, and in-app notifications.

ALTER TABLE users
    MODIFY COLUMN role ENUM('user', 'manager', 'admin') NOT NULL DEFAULT 'user',
    ADD COLUMN profile_picture_path VARCHAR(255) DEFAULT NULL AFTER apartment_number;

ALTER TABLE reservations
    ADD COLUMN last_modified_by_user_id INT UNSIGNED DEFAULT NULL AFTER cancelled_by_user_id,
    ADD CONSTRAINT fk_reservations_modified_by FOREIGN KEY (last_modified_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS notifications (
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

INSERT INTO settings (`key`, `value`, updated_at) VALUES
('site_logo_path', '', NOW())
ON DUPLICATE KEY UPDATE
`value` = VALUES(`value`),
updated_at = VALUES(updated_at);
