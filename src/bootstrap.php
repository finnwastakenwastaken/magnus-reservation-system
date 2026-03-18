<?php

declare(strict_types=1);

/**
 * Application bootstrap shared by web and CLI entry points.
 *
 * This file intentionally stops short of opening a database connection. The
 * installer and maintenance checks need config/session state even when the
 * database is not available yet.
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/support.php';

app_load_env();

$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['app']['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['app']['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $config['app']['session_secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

App\Core\Container::set('config', $config);
