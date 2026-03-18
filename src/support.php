<?php

declare(strict_types=1);

/**
 * Small procedural helpers used during the earliest bootstrap phase.
 *
 * These helpers avoid premature container/service setup when the application
 * needs to answer install or maintenance questions before the full stack loads.
 */

function app_env_path(): string
{
    return BASE_PATH . '/.env';
}

function app_load_env(): void
{
    // `.env` is optional during first boot because the installer creates it.
    $envPath = app_env_path();
    if (!is_file($envPath)) {
        return;
    }

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
