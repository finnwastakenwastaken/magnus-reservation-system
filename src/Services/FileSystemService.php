<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Small filesystem helper used by the installer and updater.
 *
 * The project intentionally avoids framework dependencies, so shared recursive
 * copy/delete/scan logic lives here instead of being repeated in services.
 */
final class FileSystemService
{
    public function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException("Unable to create directory: {$path}");
        }
    }

    public function copyRecursive(string $source, string $destination, array $exclude = []): void
    {
        $this->ensureDirectory($destination);

        $items = scandir($source);
        if ($items === false) {
            throw new \RuntimeException("Unable to read directory: {$source}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $exclude, true)) {
                continue;
            }

            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($from)) {
                $this->copyRecursive($from, $to, $exclude);
                continue;
            }

            if (!copy($from, $to)) {
                throw new \RuntimeException("Unable to copy file: {$from}");
            }
        }
    }

    public function deleteRecursive(string $path, array $preserveTopLevel = []): void
    {
        if (!file_exists($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            throw new \RuntimeException("Unable to read directory: {$path}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $preserveTopLevel, true)) {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->deleteRecursive($target);
                if (!rmdir($target)) {
                    throw new \RuntimeException("Unable to remove directory: {$target}");
                }
                continue;
            }

            if (!unlink($target)) {
                throw new \RuntimeException("Unable to remove file: {$target}");
            }
        }
    }

    public function deleteFileIfExists(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
