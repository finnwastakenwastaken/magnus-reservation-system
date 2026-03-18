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

    public function updateMany(array $input): void
    {
        $stmt = $this->db->prepare('UPDATE settings SET `value` = :value, updated_at = NOW() WHERE `key` = :key');
        foreach ($input as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => (string) $value,
            ]);
        }
        $this->cache = null;
    }
}
