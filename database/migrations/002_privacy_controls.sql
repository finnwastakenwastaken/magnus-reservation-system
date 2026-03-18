-- Privacy/GDPR support:
-- - user-managed optional contact fields
-- - per-field visibility flags
-- - pending email change verification
-- - deleted/anonymized account markers
-- - retention-related settings defaults

ALTER TABLE users
    ADD COLUMN phone_number VARCHAR(30) DEFAULT NULL AFTER apartment_number,
    ADD COLUMN contact_notes VARCHAR(255) DEFAULT NULL AFTER phone_number,
    ADD COLUMN show_phone_to_users TINYINT(1) NOT NULL DEFAULT 0 AFTER contact_notes,
    ADD COLUMN show_contact_notes_to_users TINYINT(1) NOT NULL DEFAULT 0 AFTER show_phone_to_users,
    ADD COLUMN pending_email VARCHAR(190) DEFAULT NULL AFTER activation_code_created_at,
    ADD COLUMN pending_email_token_hash VARCHAR(255) DEFAULT NULL AFTER pending_email,
    ADD COLUMN pending_email_requested_at DATETIME DEFAULT NULL AFTER pending_email_token_hash,
    ADD COLUMN pending_email_expires_at DATETIME DEFAULT NULL AFTER pending_email_requested_at,
    ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER last_login_at,
    ADD COLUMN anonymized_at DATETIME DEFAULT NULL AFTER deleted_at;

ALTER TABLE users
    ADD UNIQUE KEY uq_users_pending_email (pending_email),
    ADD KEY idx_users_deleted (deleted_at);

INSERT INTO settings (`key`, `value`, updated_at) VALUES
('retention_unactivated_days', '60', NOW()),
('retention_password_reset_days', '30', NOW()),
('retention_rate_limit_days', '30', NOW()),
('retention_update_backup_days', '30', NOW())
ON DUPLICATE KEY UPDATE
`value` = VALUES(`value`),
updated_at = VALUES(updated_at);
