<?php

declare(strict_types=1);

/**
 * Small procedural helpers used during the earliest bootstrap phase.
 *
 * These helpers avoid premature container/service setup when the application
 * needs to answer install or maintenance questions before the full stack loads.
 */

function app_primary_env_path(): string
{
    return BASE_PATH . '/.env';
}

function app_fallback_env_path(): string
{
    return BASE_PATH . '/storage/config/app.env';
}

function app_env_path(): string
{
    $primary = app_primary_env_path();
    if (is_file($primary)) {
        return $primary;
    }

    $fallback = app_fallback_env_path();
    if (is_file($fallback)) {
        return $fallback;
    }

    return $primary;
}

function app_load_env(): void
{
    // `.env` is optional during first boot because the installer creates it.
    // When the project root is not writable inside Docker, the installer can
    // persist its generated config under storage/config/app.env instead.
    $paths = array_values(array_filter([
        is_file(app_fallback_env_path()) ? app_fallback_env_path() : null,
        is_file(app_primary_env_path()) ? app_primary_env_path() : null,
    ]));
    if ($paths === []) {
        return;
    }

    foreach ($paths as $envPath) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), "\"'");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

/**
 * Read configuration values from the loaded `.env` file or the real process
 * environment.
 *
 * Docker Compose injects environment variables into the PHP process even
 * before the installer has written `.env`. Looking at `$_SERVER` and `getenv()`
 * keeps the app Docker-friendly while preserving the existing `.env` behavior.
 */
function app_env(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }

    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    return $default;
}

function app_is_installed(array $config): bool
{
    // Both the env flag and the lock file must exist so half-finished installs
    // do not accidentally look valid.
    return $config['app']['installed'] && is_file($config['app']['install_lock_path']);
}

function app_is_maintenance(array $config): bool
{
    return is_file($config['app']['maintenance_lock_path']);
}
