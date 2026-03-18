<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Persists human-readable updater status information in storage.
 *
 * The update UI reads the latest status file so administrators can see what
 * happened even if a previous update failed outside the normal request cycle.
 */
final class UpdateLogService
{
    private string $path;

    public function __construct()
    {
        $this->path = BASE_PATH . '/storage/logs/update-status.json';
    }

    public function write(array $status): void
    {
        file_put_contents($this->path, json_encode($status, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function read(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        $content = file_get_contents($this->path);
        return $content ? json_decode($content, true) : null;
    }
}
