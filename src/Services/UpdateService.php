<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use PDO;

/**
 * Coordinates the in-app update flow.
 *
 * Strategy:
 * - Prefer git-based updates for real git checkouts on mutable hosts.
 * - Fall back to GitHub archive installs for non-git mutable hosts when git is
 *   unavailable but ZipArchive/network are available.
 * - Refuse self-updates in container-centric environments like Docker/Coolify,
 *   because those should redeploy from source instead of mutating live images.
 */
final class UpdateService
{
    private array $config;
    private PDO $db;
    private UpdateLogService $statusLog;
    private MaintenanceService $maintenance;
    private BackupService $backups;
    private FileSystemService $files;

    public function __construct()
    {
        $this->config = Container::get('config');
        $this->db = Container::get('db');
        $this->statusLog = new UpdateLogService();
        $this->maintenance = new MaintenanceService();
        $this->backups = new BackupService();
        $this->files = new FileSystemService();
    }

    public function status(): array
    {
        $repoUrl = $this->config['update']['repository_url'];
        $strategy = $this->detectStrategy();
        $currentVersion = $this->config['app']['version'];
        $latest = null;
        $warning = null;

        if (!$this->config['update']['enabled']) {
            $warning = 'Updates are disabled by configuration.';
        } elseif ($this->isContainerEnvironment()) {
            $warning = 'In-app updates are disabled in container-oriented deployments. Redeploy from GitHub instead.';
        } elseif ($repoUrl === '') {
            $warning = 'Configure UPDATE_REPOSITORY_URL before checking for updates.';
        } else {
            $latest = $this->fetchLatestVersion($strategy);
            if ($latest === null) {
                $warning = 'Unable to determine the latest version from the configured repository.';
            }
        }

        return [
            'current_version' => $currentVersion,
            'latest' => $latest,
            'strategy' => $strategy,
            'supported' => $warning === null,
            'warning' => $warning,
            'status_log' => $this->statusLog->read(),
            'is_git_checkout' => is_dir(BASE_PATH . '/.git'),
            'maintenance_enabled' => $this->maintenance->isEnabled(),
            'changelog' => is_file(BASE_PATH . '/CHANGELOG.md') ? file_get_contents(BASE_PATH . '/CHANGELOG.md') : '',
        ];
    }

    public function installUpdate(array $actor): array
    {
        // Every update is explicitly staff-triggered through the protected
        // updates permission. The controller has already performed the
        // authoritative authorization check.
        $status = $this->status();
        if (!$status['supported']) {
            throw new \RuntimeException($status['warning'] ?? 'Updates are not supported in this environment.');
        }

        if ($this->isLocked()) {
            throw new \RuntimeException('Another update is already running.');
        }

        $this->lock();
        $backupPath = null;
        $result = [
            'started_at' => date(DATE_ATOM),
            'strategy' => $status['strategy'],
            'current_version' => $status['current_version'],
            'latest_version' => $status['latest']['version'] ?? null,
            'backup_path' => null,
            'migrations' => [],
        ];

        try {
            // Maintenance mode is enabled before code changes so users cannot hit
            // mixed old/new files during the update window.
            $this->maintenance->lock('update', (int) $actor['id']);
            $backupPath = $this->backups->create();
            $result['backup_path'] = $backupPath;

            if ($status['strategy'] === 'git') {
                $this->runGitUpdate();
            } elseif ($status['strategy'] === 'archive') {
                $this->runArchiveUpdate();
            } else {
                throw new \RuntimeException('No update strategy is available.');
            }

            $result['migrations'] = (new MigrationService($this->db))->migrate();
            $result['finished_at'] = date(DATE_ATOM);
            $result['success'] = true;

            $this->statusLog->write($result);
            (new AuditService())->log((int) $actor['id'], 'admin.update_installed', 'update', (string) ($result['latest_version'] ?? 'unknown'), $result);

            return $result;
        } catch (\Throwable $exception) {
            $result['finished_at'] = date(DATE_ATOM);
            $result['success'] = false;
            $result['error'] = $exception->getMessage();
            $this->statusLog->write($result);
            $this->maintenance->unlock();
            $this->unlock();
            throw $exception;
        } finally {
            $this->maintenance->unlock();
            $this->unlock();
        }
    }

    public function rollbackLatest(array $actor): array
    {
        $backups = glob($this->config['update']['backup_path'] . '/backup-*') ?: [];
        rsort($backups, SORT_STRING);
        $latestBackup = $backups[0] ?? null;
        if ($latestBackup === null) {
            throw new \RuntimeException('No backup is available for rollback.');
        }

        if ($this->isLocked()) {
            throw new \RuntimeException('Another update is already running.');
        }

        $this->lock();
        try {
            $this->maintenance->lock('rollback', (int) $actor['id']);
            $this->restoreBackup($latestBackup);

            $result = [
                'rolled_back_at' => date(DATE_ATOM),
                'backup_path' => $latestBackup,
                'success' => true,
            ];
            $this->statusLog->write($result);
            (new AuditService())->log((int) $actor['id'], 'admin.update_rolled_back', 'update', basename($latestBackup), $result);

            return $result;
        } finally {
            $this->maintenance->unlock();
            $this->unlock();
        }
    }

    private function detectStrategy(): string
    {
        $configured = $this->config['update']['strategy'];
        if ($configured !== 'auto') {
            return $configured;
        }

        if ($this->isContainerEnvironment()) {
            return 'unsupported';
        }

        if (is_dir(BASE_PATH . '/.git') && $this->commandExists($this->config['update']['git_bin'])) {
            return 'git';
        }

        if (class_exists(\ZipArchive::class)) {
            return 'archive';
        }

        return 'unsupported';
    }

    private function fetchLatestVersion(string $strategy): ?array
    {
        if ($strategy === 'git') {
            return $this->fetchLatestViaGit();
        }

        if ($strategy === 'archive') {
            return $this->fetchLatestViaGitHubApi();
        }

        return null;
    }

    private function fetchLatestViaGit(): ?array
    {
        $repo = $this->config['update']['repository_url'];
        $branch = $this->config['update']['branch'];
        $git = $this->config['update']['git_bin'];

        $tagOutput = $this->runCommand(sprintf(
            '%s ls-remote --tags %s',
            $this->escapeCommandPart($git),
            $this->escapeCommandPart($repo)
        ));
        $latestTag = $this->latestTagFromLsRemote($tagOutput);

        $branchOutput = $this->runCommand(sprintf(
            '%s ls-remote %s refs/heads/%s',
            $this->escapeCommandPart($git),
            $this->escapeCommandPart($repo),
            $this->escapeCommandPart($branch)
        ));
        $branchCommit = trim((string) strtok($branchOutput, "\t"));

        return [
            'version' => $latestTag ?: $branchCommit,
            'release_notes' => is_file(BASE_PATH . '/CHANGELOG.md') ? file_get_contents(BASE_PATH . '/CHANGELOG.md') : '',
            'published_at' => null,
        ];
    }

    private function fetchLatestViaGitHubApi(): ?array
    {
        $repoSlug = $this->githubSlug();
        if ($repoSlug === null) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: MAGNUS-Reservation-System\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $releaseResponse = @file_get_contents("https://api.github.com/repos/{$repoSlug}/releases/latest", false, $context);
        if ($releaseResponse !== false) {
            $release = json_decode($releaseResponse, true);
            if (is_array($release) && isset($release['tag_name'])) {
                return [
                    'version' => $release['tag_name'],
                    'release_notes' => (string) ($release['body'] ?? ''),
                    'published_at' => $release['published_at'] ?? null,
                ];
            }
        }

        $branch = $this->config['update']['branch'];
        $branchResponse = @file_get_contents("https://api.github.com/repos/{$repoSlug}/commits/{$branch}", false, $context);
        if ($branchResponse === false) {
            return null;
        }

        $commit = json_decode($branchResponse, true);
        return [
            'version' => $commit['sha'] ?? null,
            'release_notes' => $commit['commit']['message'] ?? '',
            'published_at' => $commit['commit']['committer']['date'] ?? null,
        ];
    }

    private function runGitUpdate(): void
    {
        // `--ff-only` avoids merge commits and forces the deployment to remain a
        // clean fast-forward of the configured branch.
        $git = $this->config['update']['git_bin'];
        $branch = $this->config['update']['branch'];
        $repo = $this->config['update']['repository_url'];

        $this->runCommand(sprintf(
            '%s fetch --tags %s %s',
            $this->escapeCommandPart($git),
            $this->escapeCommandPart($repo),
            $this->escapeCommandPart($branch)
        ), BASE_PATH);
        $this->runCommand(sprintf(
            '%s pull --ff-only %s %s',
            $this->escapeCommandPart($git),
            $this->escapeCommandPart($repo),
            $this->escapeCommandPart($branch)
        ), BASE_PATH);
    }

    private function runArchiveUpdate(): void
    {
        $repoSlug = $this->githubSlug();
        if ($repoSlug === null) {
            throw new \RuntimeException('Repository URL is not a supported GitHub repository URL.');
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive is required for archive-based updates.');
        }

        $branch = $this->config['update']['branch'];
        $tempRoot = $this->config['update']['temp_path'];
        $this->files->ensureDirectory($tempRoot);

        $zipPath = $tempRoot . '/update-' . date('Ymd-His') . '.zip';
        $extractPath = $tempRoot . '/extract-' . date('Ymd-His');
        $archiveUrl = "https://codeload.github.com/{$repoSlug}/zip/refs/heads/{$branch}";

        $data = @file_get_contents($archiveUrl);
        if ($data === false) {
            throw new \RuntimeException('Unable to download the update archive from GitHub.');
        }

        file_put_contents($zipPath, $data);
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Unable to open the downloaded archive.');
        }
        $this->files->ensureDirectory($extractPath);
        $zip->extractTo($extractPath);
        $zip->close();

        $subdirs = array_values(array_filter(scandir($extractPath) ?: [], static fn(string $item): bool => $item !== '.' && $item !== '..'));
        $sourceRoot = $extractPath . '/' . ($subdirs[0] ?? '');
        if (!is_dir($sourceRoot)) {
            throw new \RuntimeException('The downloaded archive did not contain a valid project root.');
        }

        $this->replaceCodeFrom($sourceRoot);
    }

    private function replaceCodeFrom(string $sourceRoot): void
    {
        // Secrets and writable runtime data are intentionally preserved across
        // archive-based updates; only versioned application files are replaced.
        // Public uploads stay intact so logos and profile pictures survive.
        $preserve = ['.env', 'storage', 'public/uploads', '.git', '.idea'];
        $this->files->deleteRecursive(BASE_PATH, $preserve);
        $this->files->copyRecursive($sourceRoot, BASE_PATH, $preserve);
    }

    private function restoreBackup(string $backupPath): void
    {
        $preserve = ['.env', 'storage', '.git', '.idea'];
        $this->files->deleteRecursive(BASE_PATH, $preserve);
        $this->files->copyRecursive($backupPath, BASE_PATH, $preserve);
    }

    private function latestTagFromLsRemote(string $output): ?string
    {
        $tags = [];
        foreach (preg_split('/\R/', trim($output)) as $line) {
            if ($line === '' || !str_contains($line, 'refs/tags/')) {
                continue;
            }

            $ref = substr((string) strstr($line, 'refs/tags/'), strlen('refs/tags/'));
            if (str_ends_with($ref, '^{}')) {
                $ref = substr($ref, 0, -3);
            }
            $tags[] = $ref;
        }

        if ($tags === []) {
            return null;
        }

        usort($tags, 'version_compare');
        return end($tags) ?: null;
    }

    private function githubSlug(): ?string
    {
        $repo = rtrim($this->config['update']['repository_url'], '/');
        if (preg_match('#github\.com[:/]+([^/]+/[^/]+?)(?:\.git)?$#', $repo, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function commandExists(string $binary): bool
    {
        try {
            $this->runCommand(sprintf('%s --version', $this->escapeCommandPart($binary)));
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function runCommand(string $command, ?string $cwd = null): string
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd ?? BASE_PATH);
        if (!is_resource($process)) {
            throw new \RuntimeException("Unable to start command: {$command}");
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim($stderr) !== '' ? trim($stderr) : "Command failed: {$command}");
        }

        return trim($stdout);
    }

    private function isContainerEnvironment(): bool
    {
        return is_file('/.dockerenv')
            || !empty($_ENV['COOLIFY_URL'])
            || !empty($_ENV['K_SERVICE']);
    }

    private function escapeCommandPart(string $value): string
    {
        return escapeshellarg($value);
    }

    private function isLocked(): bool
    {
        return is_file($this->config['app']['update_lock_path']);
    }

    private function lock(): void
    {
        file_put_contents($this->config['app']['update_lock_path'], date(DATE_ATOM));
    }

    private function unlock(): void
    {
        if ($this->isLocked()) {
            unlink($this->config['app']['update_lock_path']);
        }
    }
}
