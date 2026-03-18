<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Container;
use PDO;

/**
 * Lightweight user lookup model.
 *
 * More complex account workflows live in UserService; this class exists mainly
 * for simple current-user hydration in the auth layer.
 */
final class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Container::get('db');
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }
}
