<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use PDO;

/**
 * Access layer for admin-configurable application settings.
 *
 * Reservation validation reads booking limits from here so admin changes take
 * effect immediately without code changes.
 */
final class SettingsService
{
    private PDO $db;
    private ?array $cache = null;

    public function __construct()
    {
        $this->db = Container::get('db');
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = $this->db->query('SELECT `key`, `value` FROM settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $this->cache = $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bookingRules(): array
    {
        return [
            'start_hour' => (int) $this->get('booking_start_hour', 9),
            'end_hour' => (int) $this->get('booking_end_hour', 22),
            'max_week_hours' => (int) $this->get('max_hours_per_week', 6),
            'max_month_hours' => (int) $this->get('max_hours_per_month', 12),
            'timezone' => (string) $this->get('timezone', Container::get('config')['app']['timezone']),
        ];
    }

    public function siteLogoPath(): ?string
    {
        $path = (string) $this->get('site_logo_path', '');

        return $path !== '' ? $path : null;
    }

    /**
     * External mail delivery configuration.
     *
     * Database settings are the active source of truth after installation.
     * Environment variables remain as fallback-only bootstrap values.
     */
    public function mailjetConfig(): array
    {
        return [
            'enabled' => $this->boolSetting('mailjet_enabled', filter_var(Container::get('config')['mailjet']['enabled'] ?? false, FILTER_VALIDATE_BOOL)),
            'api_key' => (string) $this->get('mailjet_api_key', Container::get('config')['mailjet']['api_key'] ?? ''),
            'api_secret' => (string) $this->get('mailjet_api_secret', Container::get('config')['mailjet']['api_secret'] ?? ''),
            'from_email' => (string) $this->get('mail_from_email', Container::get('config')['mailjet']['from_email'] ?? 'no-reply@example.com'),
            'from_name' => (string) $this->get('mail_from_name', Container::get('config')['mailjet']['from_name'] ?? 'Living Room App'),
        ];
    }

    /**
     * Cloudflare Turnstile configuration.
     */
    public function turnstileConfig(): array
    {
        $fallback = Container::get('config')['turnstile'] ?? [];

        return [
            'enabled' => $this->boolSetting('turnstile_enabled', (bool) ($fallback['enabled'] ?? false)),
            'site_key' => (string) $this->get('turnstile_site_key', $fallback['site_key'] ?? ''),
            'secret_key' => (string) $this->get('turnstile_secret_key', $fallback['secret_key'] ?? ''),
        ];
    }

    public function secretMask(string $key): string
    {
        $value = (string) $this->get($key, '');
        if ($value === '') {
            return '';
        }

        return str_repeat('*', max(8, min(24, strlen($value))));
    }

    public function updateMany(array $input): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO settings (`key`, `value`, updated_at)
             VALUES (:key, :value, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)'
        );
        foreach ($input as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => (string) $value,
            ]);
        }
        $this->cache = null;
    }

    private function boolSetting(string $key, bool $default): bool
    {
        return filter_var($this->get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOL);
    }
}
