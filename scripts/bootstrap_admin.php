<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Container;
use App\Core\Database;

if ($argc < 6) {
    fwrite(STDERR, "Usage: php scripts/bootstrap_admin.php <first_name> <last_name> <email> <apartment_number> <password>\n");
    exit(1);
}

[$script, $firstName, $lastName, $email, $apartment, $password] = $argv;
Container::set('db', Database::connection());
$db = Container::get('db');

$stmt = $db->prepare(
    'INSERT INTO users (
        first_name, last_name, email, apartment_number, password_hash, role, is_active, activated_at, created_at, updated_at
     ) VALUES (
        :first_name, :last_name, :email, :apartment_number, :password_hash, :role, 1, NOW(), NOW(), NOW()
     )'
);

$stmt->execute([
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => strtolower($email),
    'apartment_number' => $apartment,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'admin',
]);

fwrite(STDOUT, "Admin user created.\n");
