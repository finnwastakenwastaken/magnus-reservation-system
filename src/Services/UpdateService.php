<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;

/**
 * Docker-only deployment update status helper.
 *
 * The project no longer supports in-app self-mutation. Administrators are
 * shown the current version and the supported Docker Compose update commands,
 * but actual code updates must happen outside the application with Git and
 * `docker compose up -d --build`.
 */
final class UpdateService
{
    private array $config;
    private UpdateLogService $statusLog;
    private MaintenanceService $maintenance;

    public function __construct()
    {
        $this->config = Container::get('config');
        $this->statusLog = new UpdateLogService();
        $this->maintenance = new MaintenanceService();
    }

    public function status(): array
    {
        return [
            'current_version' => $this->config['app']['version'],
            'latest' => null,
            'strategy' => 'docker-compose',
            'supported' => false,
            'warning' => 'In-app updates are disabled. Update the deployment with git pull and docker compose up -d --build.',
            'status_log' => $this->statusLog->read(),
            'is_git_checkout' => false,
            'maintenance_enabled' => $this->maintenance->isEnabled(),
            'changelog' => is_file(BASE_PATH . '/CHANGELOG.md') ? file_get_contents(BASE_PATH . '/CHANGELOG.md') : '',
            'commands' => [
                'git pull',
                'docker compose up -d --build',
                'docker compose exec app php scripts/migrate.php',
                'docker compose logs -f app',
            ],
        ];
    }

    public function installUpdate(array $actor): array
    {
        throw new \RuntimeException('In-app updates are disabled for the Docker Compose deployment. Use git pull and docker compose up -d --build instead.');
    }

    public function rollbackLatest(array $actor): array
    {
        throw new \RuntimeException('Rollback is not available inside the Docker Compose deployment. Roll back with Git and rebuild the stack instead.');
    }
}
