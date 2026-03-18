<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * Performs the one-time installation workflow triggered by /install.
 *
 * The installer owns:
 * - database connectivity validation
 * - optional database creation
 * - schema/bootstrap migration execution
 * - first admin account creation
 * - writing `.env`
 * - writing the install lock file
 */
final class InstallerService
{
    /**
     * Execute the installer end-to-end.
     *
     * The method is designed to be rerunnable after partial failures. Schema
     * creation is idempotent and admin creation is skipped if an admin already
     * exists, allowing the installer to complete the config/lock steps later.
     */
    public function install(array $input): void
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            throw new \App\Core\ValidationException($errors);
        }

        $appUrl = rtrim((string) $input['app_url'], '/');
        $dbName = (string) $input['db_database'];
        $charset = 'utf8mb4';
        $baseDsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $input['db_host'],
            (int) $input['db_port'],
            $charset
        );

        $schemaDsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $input['db_host'],
            (int) $input['db_port'],
            $dbName,
            $charset
        );

        try {
            $serverPdo = new PDO($baseDsn, (string) $input['db_username'], (string) $input['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new \RuntimeException('Could not connect to the database server with the provided credentials.');
        }

        $databaseCreated = false;
        try {
            $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $databaseCreated = true;
        } catch (PDOException $exception) {
            $checkStmt = $serverPdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :database_name');
            $checkStmt->execute(['database_name' => $dbName]);
            if (!$checkStmt->fetchColumn()) {
                throw new \RuntimeException('The database could not be created and does not appear to exist. Create it manually or grant CREATE DATABASE permission.');
            }
        }

        try {
            $pdo = new PDO($schemaDsn, (string) $input['db_username'], (string) $input['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new \RuntimeException('Connected to the server, but could not open the selected database.');
        }

        $pdo->beginTransaction();
        try {
            // The baseline schema is still imported for fresh installs so the
            // installer remains simple. Immediately after that, the versioned
            // migration runner records/applies any tracked SQL migrations.
            $this->runSchema($pdo, BASE_PATH . '/database/schema.sql');
            (new MigrationService($pdo))->migrate();

            $adminStmt = $pdo->prepare('SELECT id FROM users WHERE role = :role ORDER BY id ASC LIMIT 1');
            $adminStmt->execute(['role' => 'admin']);
            if (!$adminStmt->fetchColumn()) {
                $insertAdmin = $pdo->prepare(
                    'INSERT INTO users (
                        first_name, last_name, email, apartment_number, password_hash, role, is_active, activated_at, created_at, updated_at
                     ) VALUES (
                        :first_name, :last_name, :email, :apartment_number, :password_hash, :role, 1, NOW(), NOW(), NOW()
                     )'
                );
                $insertAdmin->execute([
                    'first_name' => trim((string) $input['admin_first_name']),
                    'last_name' => trim((string) $input['admin_last_name']),
                    'email' => strtolower(trim((string) $input['admin_email'])),
                    'apartment_number' => 'ADMIN',
                    'password_hash' => password_hash((string) $input['admin_password'], PASSWORD_DEFAULT),
                    'role' => 'admin',
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        (new EnvWriter())->write([
            'APP_NAME' => $_ENV['APP_NAME'] ?? 'Shared Living Room Reservations',
            'APP_ENV' => $_ENV['APP_ENV'] ?? 'production',
            'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? 'false',
            'APP_INSTALLED' => 'true',
            'APP_VERSION' => $_ENV['APP_VERSION'] ?? (is_file(BASE_PATH . '/VERSION') ? trim((string) file_get_contents(BASE_PATH . '/VERSION')) : '0.1.0'),
            'APP_URL' => $appUrl,
            'APP_TIMEZONE' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Amsterdam',
            'APP_LOCALE' => $_ENV['APP_LOCALE'] ?? 'en',
            'SESSION_NAME' => $_ENV['SESSION_NAME'] ?? 'livingroom_session',
            'SESSION_SECURE' => str_starts_with($appUrl, 'https://') ? 'true' : ($_ENV['SESSION_SECURE'] ?? 'false'),
            'DB_HOST' => $input['db_host'],
            'DB_PORT' => $input['db_port'],
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $input['db_username'],
            'DB_PASSWORD' => $input['db_password'],
            'DB_CHARSET' => 'utf8mb4',
            'ADMIN_EMAIL' => strtolower(trim((string) $input['admin_email'])),
            'PAGINATION_SIZE' => $_ENV['PAGINATION_SIZE'] ?? '10',
            'MAILJET_ENABLED' => $_ENV['MAILJET_ENABLED'] ?? 'false',
            'MAILJET_API_KEY' => $_ENV['MAILJET_API_KEY'] ?? '',
            'MAILJET_API_SECRET' => $_ENV['MAILJET_API_SECRET'] ?? '',
            'MAIL_FROM_EMAIL' => $_ENV['MAIL_FROM_EMAIL'] ?? 'no-reply@example.com',
            'MAIL_FROM_NAME' => $_ENV['MAIL_FROM_NAME'] ?? 'Living Room App',
            'TURNSTILE_SITE_KEY' => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
            'TURNSTILE_SECRET_KEY' => $_ENV['TURNSTILE_SECRET_KEY'] ?? '',
            'UPDATE_ENABLED' => $_ENV['UPDATE_ENABLED'] ?? 'true',
            'UPDATE_REPOSITORY_URL' => $_ENV['UPDATE_REPOSITORY_URL'] ?? '',
            'UPDATE_REPOSITORY_BRANCH' => $_ENV['UPDATE_REPOSITORY_BRANCH'] ?? 'main',
            'UPDATE_STRATEGY' => $_ENV['UPDATE_STRATEGY'] ?? 'auto',
            'UPDATE_GIT_BIN' => $_ENV['UPDATE_GIT_BIN'] ?? 'git',
            'UPDATE_CHECK_AUTOMATIC' => $_ENV['UPDATE_CHECK_AUTOMATIC'] ?? 'false',
            'UPDATE_BACKUP_PATH' => $_ENV['UPDATE_BACKUP_PATH'] ?? 'storage/backups',
            'UPDATE_TEMP_PATH' => $_ENV['UPDATE_TEMP_PATH'] ?? 'storage/updates',
        ]);

        $lockPath = BASE_PATH . '/storage/installed.lock';
        $lockContent = json_encode([
            'installed_at' => date(DATE_ATOM),
            'database_created' => $databaseCreated,
            'app_url' => $appUrl,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (@file_put_contents($lockPath, $lockContent) === false) {
            throw new \RuntimeException('Installation completed, but the install lock file could not be written.');
        }
    }

    private function validate(array $input): array
    {
        $errors = [];

        foreach (['db_host', 'db_port', 'db_database', 'db_username', 'app_url', 'admin_first_name', 'admin_last_name', 'admin_email', 'admin_password'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[$field] = 'installer.required';
            }
        }

        if (!filter_var((string) ($input['app_url'] ?? ''), FILTER_VALIDATE_URL)) {
            $errors['app_url'] = 'installer.invalid_url';
        }
        if (!filter_var((string) ($input['admin_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'validation.email_invalid';
        }
        if ((int) ($input['db_port'] ?? 0) < 1 || (int) ($input['db_port'] ?? 0) > 65535) {
            $errors['db_port'] = 'installer.invalid_port';
        }
        if (strlen((string) ($input['admin_password'] ?? '')) < 12) {
            $errors['admin_password'] = 'validation.password_length';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) ($input['db_database'] ?? ''))) {
            $errors['db_database'] = 'installer.invalid_database';
        }

        return $errors;
    }

    private function runSchema(PDO $pdo, string $schemaPath): void
    {
        // SQL is intentionally split naively because the shipped schema/migrations
        // avoid stored procedures, custom delimiters, and other complex syntax.
        $sql = file_get_contents($schemaPath);
        if ($sql === false) {
            throw new \RuntimeException('Could not read the schema file.');
        }

        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }
}
