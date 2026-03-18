<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;

/**
 * Creates lightweight code backups before an update is applied.
 *
 * The backup deliberately excludes runtime-only data such as logs and temp
 * update workspaces. `.env`, `storage/`, and public uploads are preserved or
 * restored separately so secrets and generated files survive update flows.
 */
final class BackupService
{
    public function __construct(private readonly FileSystemService $files = new FileSystemService())
    {
    }

    public function create(): string
    {
        $config = Container::get('config')['update'];
        $backupRoot = $config['backup_path'];
        $this->files->ensureDirectory($backupRoot);

        $backupPath = $backupRoot . '/backup-' . date('Ymd-His');
        $this->files->copyRecursive(BASE_PATH, $backupPath, ['.git', 'storage']);

        return $backupPath;
    }
}
