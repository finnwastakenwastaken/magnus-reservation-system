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
        'name' => (string) app_env('APP_NAME', 'Shared Living Room Reservations'),
        'env' => (string) app_env('APP_ENV', 'production'),
        'debug' => filter_var(app_env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
        'installed' => filter_var(app_env('APP_INSTALLED', false), FILTER_VALIDATE_BOOL),
        'version' => trim((string) (is_file(BASE_PATH . '/VERSION') ? file_get_contents(BASE_PATH . '/VERSION') : app_env('APP_VERSION', '0.2.0'))),
        'url' => rtrim((string) app_env('APP_URL', 'http://localhost'), '/'),
        'timezone' => (string) app_env('APP_TIMEZONE', 'Europe/Amsterdam'),
        'locale' => (string) app_env('APP_LOCALE', 'en'),
        'fallback_locale' => 'en',
        'session_name' => (string) app_env('SESSION_NAME', 'livingroom_session'),
        'session_secure' => filter_var(app_env('SESSION_SECURE', false), FILTER_VALIDATE_BOOL),
        'admin_email' => (string) app_env('ADMIN_EMAIL', 'admin@example.com'),
        'pagination_size' => (int) app_env('PAGINATION_SIZE', 10),
        'install_lock_path' => BASE_PATH . '/storage/installed.lock',
        'maintenance_lock_path' => BASE_PATH . '/storage/maintenance.lock',
    ],
    'db' => [
        'host' => (string) app_env('DB_HOST', 'db'),
        'port' => (int) app_env('DB_PORT', 3306),
        'database' => (string) app_env('DB_DATABASE', 'living_room'),
        'username' => (string) app_env('DB_USERNAME', 'living_room'),
        'password' => (string) app_env('DB_PASSWORD', 'change_me_database_password'),
        'charset' => (string) app_env('DB_CHARSET', 'utf8mb4'),
    ],
    'mailjet' => [
        'enabled' => filter_var(app_env('MAILJET_ENABLED', false), FILTER_VALIDATE_BOOL),
        'api_key' => (string) app_env('MAILJET_API_KEY', ''),
        'api_secret' => (string) app_env('MAILJET_API_SECRET', ''),
        'from_email' => (string) app_env('MAIL_FROM_EMAIL', 'no-reply@example.com'),
        'from_name' => (string) app_env('MAIL_FROM_NAME', 'Living Room App'),
    ],
    'turnstile' => [
        'enabled' => app_env('TURNSTILE_SECRET_KEY', '') !== '' && app_env('TURNSTILE_SITE_KEY', '') !== '',
        'site_key' => (string) app_env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => (string) app_env('TURNSTILE_SECRET_KEY', ''),
    ],
];
