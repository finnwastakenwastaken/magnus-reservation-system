ALTER TABLE messages
    ADD COLUMN conversation_key VARCHAR(191) NOT NULL DEFAULT '' AFTER id;

UPDATE messages
SET conversation_key = CONCAT(
    'direct:',
    LEAST(sender_user_id, recipient_user_id),
    ':',
    GREATEST(sender_user_id, recipient_user_id)
)
WHERE conversation_key = '';

ALTER TABLE messages
    ADD KEY idx_messages_conversation_created (conversation_key, created_at);

INSERT INTO settings (`key`, `value`, updated_at) VALUES
('mailjet_enabled', '0', NOW()),
('mailjet_api_key', '', NOW()),
('mailjet_api_secret', '', NOW()),
('mail_from_email', 'no-reply@example.com', NOW()),
('mail_from_name', 'Living Room App', NOW()),
('turnstile_enabled', '0', NOW()),
('turnstile_site_key', '', NOW()),
('turnstile_secret_key', '', NOW())
ON DUPLICATE KEY UPDATE
`value` = VALUES(`value`),
updated_at = VALUES(updated_at);

INSERT INTO permissions (code, name, description, created_at) VALUES
('users.verify', 'Verify users', 'Manually activate pending resident accounts.', NOW()),
('integrations.manage', 'Manage integrations', 'Configure Mailjet and Cloudflare Turnstile settings.', NOW()),
('messages.broadcast', 'Broadcast messages', 'Send admin announcements to all users or selected roles.', NOW())
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.code = 'users.verify'
WHERE r.slug = 'manager'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.code IN ('users.verify', 'integrations.manage', 'messages.broadcast')
WHERE r.slug = 'admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
