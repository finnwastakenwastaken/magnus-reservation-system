<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Writes runtime configuration for the Docker Compose deployment.
 *
 * In this project the durable runtime configuration belongs in
 * `storage/config/app.env`, because the application image itself is rebuilt and
 * replaced during normal Docker-based updates. Writing to the container's root
 * filesystem would be ephemeral.
 */
final class EnvWriter
{
    public function write(array $values): void
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $stringValue = (string) $value;
            if ($stringValue === '' || preg_match('/\s/', $stringValue) === 1) {
                $stringValue = '"' . addcslashes($stringValue, "\"\\") . '"';
            }
            $lines[] = $key . '=' . $stringValue;
        }

        $content = implode(PHP_EOL, $lines) . PHP_EOL;
        $path = $this->resolveWritableEnvPath();
        if (@file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Unable to write the environment config file. Check project or storage/config permissions.');
        }
    }

    private function resolveWritableEnvPath(): string
    {
        $fallback = app_fallback_env_path();
        $fallbackDir = dirname($fallback);
        if (!is_dir($fallbackDir) && !@mkdir($fallbackDir, 0775, true) && !is_dir($fallbackDir)) {
            throw new \RuntimeException('Unable to prepare the fallback config directory under storage/config.');
        }

        if ($this->isWritableTarget($fallback)) {
            return $fallback;
        }

        $primary = app_primary_env_path();
        if ($this->isWritableTarget($primary)) {
            return $primary;
        }

        throw new \RuntimeException('Unable to write configuration to either `.env` or `storage/config/app.env`.');
    }

    private function isWritableTarget(string $path): bool
    {
        if (is_file($path)) {
            return is_writable($path);
        }

        return is_dir(dirname($path)) && is_writable(dirname($path));
    }
}
