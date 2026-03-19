<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use PDO;

/**
 * Records security-relevant and admin actions in the audit_log table.
 *
 * Every entry captures the acting user when available, a stable action key,
 * the logical target type/identifier, contextual metadata, and the request IP.
 * Controllers and services should prefer action names that read like
 * `domain.verb` so downstream review stays consistent.
 */
final class AuditService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Container::get('db');
    }

    public function log(?int $actorUserId, string $eventType, string $entityType, string $entityId, array $details = []): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_log (actor_user_id, event_type, entity_type, entity_id, details_json, ip_address, created_at)
             VALUES (:actor_user_id, :event_type, :entity_type, :entity_id, :details_json, :ip_address, NOW())'
        );
        $stmt->execute([
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details_json' => $details !== [] ? json_encode($details, JSON_THROW_ON_ERROR) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
