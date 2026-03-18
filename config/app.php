<?php

declare(strict_types=1);

/**
 * Central configuration map built from environment variables.
 *
 * Keeping configuration in one place makes it easier for the installer,
 * deployment docs, and updater to stay aligned about supported settings.
 */

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'Shared Living Room Reservations',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
        'installed' => filter_var($_ENV['APP_INSTALLED'] ?? false, FILTER_VALIDATE_BOOL),
        'version' => trim((string) (is_file(BASE_PATH . '/VERSION') ? file_get_contents(BASE_PATH . '/VERSION') : ($_ENV['APP_VERSION'] ?? '0.1.0'))),
        'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Amsterdam',
        'locale' => $_ENV['APP_LOCALE'] ?? 'en',
        'fallback_locale' => 'en',
        'session_name' => $_ENV['SESSION_NAME'] ?? 'livingroom_session',
        'session_secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOL),
        'admin_email' => $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com',
        'pagination_size' => (int) ($_ENV['PAGINATION_SIZE'] ?? 10),
        'install_lock_path' => BASE_PATH . '/storage/installed.lock',
        'maintenance_lock_path' => BASE_PATH . '/storage/maintenance.lock',
        'update_lock_path' => BASE_PATH . '/storage/update.lock',
    ],
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'living_room',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ],
    'mailjet' => [
        'enabled' => filter_var($_ENV['MAILJET_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
        'api_key' => $_ENV['MAILJET_API_KEY'] ?? '',
        'api_secret' => $_ENV['MAILJET_API_SECRET'] ?? '',
        'from_email' => $_ENV['MAIL_FROM_EMAIL'] ?? 'no-reply@example.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Living Room App',
    ],
    'turnstile' => [
        'enabled' => !empty($_ENV['TURNSTILE_SECRET_KEY']) && !empty($_ENV['TURNSTILE_SITE_KEY']),
        'site_key' => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
        'secret_key' => $_ENV['TURNSTILE_SECRET_KEY'] ?? '',
    ],
    'update' => [
        'enabled' => filter_var($_ENV['UPDATE_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
        'repository_url' => $_ENV['UPDATE_REPOSITORY_URL'] ?? '',
        'branch' => $_ENV['UPDATE_REPOSITORY_BRANCH'] ?? 'main',
        'strategy' => $_ENV['UPDATE_STRATEGY'] ?? 'auto',
        'git_bin' => $_ENV['UPDATE_GIT_BIN'] ?? 'git',
        'automatic_check' => filter_var($_ENV['UPDATE_CHECK_AUTOMATIC'] ?? false, FILTER_VALIDATE_BOOL),
        'backup_path' => BASE_PATH . '/' . trim($_ENV['UPDATE_BACKUP_PATH'] ?? 'storage/backups', '/\\'),
        'temp_path' => BASE_PATH . '/' . trim($_ENV['UPDATE_TEMP_PATH'] ?? 'storage/updates', '/\\'),
    ],
];
