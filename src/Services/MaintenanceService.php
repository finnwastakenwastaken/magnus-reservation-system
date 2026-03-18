<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;

/**
 * Controls maintenance mode used during in-app updates.
 *
 * Maintenance mode is file-based so it works before the full application stack
 * has booted and can still protect the app if the update process fails midway.
 */
final class MaintenanceService
{
    public function lock(string $reason, ?int $actorUserId = null): void
    {
        $path = Container::get('config')['app']['maintenance_lock_path'];
        $payload = json_encode([
            'reason' => $reason,
            'actor_user_id' => $actorUserId,
            'enabled_at' => date(DATE_ATOM),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        file_put_contents($path, $payload);
    }

    public function unlock(): void
    {
        $path = Container::get('config')['app']['maintenance_lock_path'];
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function isEnabled(): bool
    {
        return is_file(Container::get('config')['app']['maintenance_lock_path']);
    }

    public function details(): ?array
    {
        $path = Container::get('config')['app']['maintenance_lock_path'];
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        return $content ? json_decode($content, true) : null;
    }
}
