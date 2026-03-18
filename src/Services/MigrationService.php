<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use PDO;

/**
 * Executes ordered SQL migrations from database/migrations.
 *
 * The application started with a single schema file. This service adds a
 * versioned migration path so future in-app updates can safely apply database
 * changes in a predictable order.
 */
final class MigrationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Container::get('db');
    }

    public function migrate(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->appliedMigrations();
        $executed = [];

        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Unable to read migration file: {$name}");
            }

            $this->db->beginTransaction();
            try {
                foreach ($this->splitSql($sql) as $statement) {
                    $this->db->exec($statement);
                }
                $stmt = $this->db->prepare('INSERT INTO migrations (migration_name, applied_at) VALUES (:migration_name, NOW())');
                $stmt->execute(['migration_name' => $name]);
                $this->db->commit();
                $executed[] = $name;
            } catch (\Throwable $exception) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $exception;
            }
        }

        return $executed;
    }

    public function appliedMigrations(): array
    {
        $this->ensureMigrationsTable();
        $rows = $this->db->query('SELECT migration_name FROM migrations ORDER BY migration_name ASC')->fetchAll();

        return array_map(static fn(array $row): string => $row['migration_name'], $rows);
    }

    /**
     * Record the current migration files as already applied.
     *
     * Fresh installs import the latest baseline schema directly, so replaying
     * every historical migration would duplicate table/column changes. This
     * helper marks those files as applied without re-executing them.
     */
    public function markAllAsApplied(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->appliedMigrations();
        $stmt = $this->db->prepare(
            'INSERT INTO migrations (migration_name, applied_at)
             VALUES (:migration_name, NOW())'
        );

        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $stmt->execute(['migration_name' => $name]);
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(190) NOT NULL,
                applied_at DATETIME NOT NULL,
                UNIQUE KEY uq_migration_name (migration_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function migrationFiles(): array
    {
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    private function splitSql(string $sql): array
    {
        return array_values(array_filter(array_map('trim', explode(';', $sql))));
    }
}
