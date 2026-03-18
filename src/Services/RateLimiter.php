<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use PDO;

/**
 * Basic table-backed rate limiter.
 *
 * It is deliberately simple but effective enough for login, activation, signup,
 * and messaging abuse throttling without additional infrastructure.
 */
final class RateLimiter
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Container::get('db');
    }

    public function hit(string $actionKey, string $bucketKey, int $maxHits, int $windowSeconds): bool
    {
        $now = new \DateTimeImmutable('now');
        $windowStart = $now->modify("-{$windowSeconds} seconds")->format('Y-m-d H:i:s');

        $this->db->prepare(
            'DELETE FROM rate_limits WHERE updated_at < :window_start'
        )->execute(['window_start' => $windowStart]);

        $stmt = $this->db->prepare('SELECT * FROM rate_limits WHERE action_key = :action_key AND bucket_key = :bucket_key LIMIT 1');
        $stmt->execute([
            'action_key' => $actionKey,
            'bucket_key' => $bucketKey,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            $insert = $this->db->prepare(
                'INSERT INTO rate_limits (action_key, bucket_key, hits, window_started_at, updated_at)
                 VALUES (:action_key, :bucket_key, 1, NOW(), NOW())'
            );
            $insert->execute([
                'action_key' => $actionKey,
                'bucket_key' => $bucketKey,
            ]);

            return true;
        }

        if ((int) $row['hits'] >= $maxHits) {
            return false;
        }

        $update = $this->db->prepare('UPDATE rate_limits SET hits = hits + 1, updated_at = NOW() WHERE id = :id');
        $update->execute(['id' => $row['id']]);

        return true;
    }
}
