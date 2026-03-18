<?php

declare(strict_types=1);

/**
 * Front controller for every HTTP request.
 *
 * Responsibilities:
 * - load bootstrap/config/session state
 * - redirect fresh installs into the installer
 * - enforce maintenance mode before normal routing
 * - delegate the final request to the application router
 *
 * Keeping these cross-cutting checks here ensures they still work even when the
 * rest of the app is unavailable during install/update operations.
 */

use App\Core\App;
use App\Core\Auth;
use App\Core\Container;
use App\Core\Database;
use App\Core\Response;

require dirname(__DIR__) . '/src/bootstrap.php';

$config = \App\Core\Container::get('config');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = rtrim($path, '/') ?: '/';
$installed = app_is_installed($config);

if (!$installed && $normalizedPath !== '/install') {
    Response::redirect('/install')->send();
    exit;
}

if ($installed && $normalizedPath === '/install') {
    (new Response('Not Found', 404))->send();
    exit;
}

if ($installed && app_is_maintenance($config)) {
    $adminCanPass = false;

    try {
        Container::set('db', Database::connection());
        $user = Auth::user();
        $adminCanPass = $user !== null
            && Auth::hasPermission(\App\Security\Permissions::UPDATES_MANAGE)
            && (str_starts_with($normalizedPath, '/admin/updates') || $normalizedPath === '/login' || str_starts_with($normalizedPath, '/assets/'));
    } catch (\Throwable) {
        $adminCanPass = false;
    }

    if (!$adminCanPass) {
        (new Response(file_get_contents(dirname(__DIR__) . '/src/Views/errors/maintenance.html') ?: 'Maintenance', 503))->send();
        exit;
    }
}

$app = new App();
$app->run();
