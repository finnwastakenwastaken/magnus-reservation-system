<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\InstallController;
use App\Controllers\MessageController;
use App\Controllers\ReservationController;

/**
 * Main HTTP application coordinator.
 *
 * It wires infrastructure services into the container and registers every route
 * for the plain-PHP controller layer. Database setup is conditional so the
 * installer can render before a connection exists.
 */
final class App
{
    /**
     * Boot the service container, dispatch the current request, and convert
     * uncaught exceptions into user-facing error responses.
     */
    public function run(): void
    {
        $this->bootstrapServices();

        $router = new Router();
        $this->registerRoutes($router);

        try {
            $response = $router->dispatch(Request::capture());
        } catch (\Throwable $exception) {
            $response = ErrorHandler::handle($exception);
        }

        $response->send();
    }

    private function bootstrapServices(): void
    {
        // The installer runs without a database connection; every other request
        // expects PDO to be available via the container.
        if (app_is_installed(Container::get('config'))) {
            Container::set('db', Database::connection());
        }
        Container::set('translator', new Translator());
        Container::set('view', new View());
    }

    private function registerRoutes(Router $router): void
    {
        // Routes stay intentionally explicit to keep the application easy to
        // follow without a framework or annotation system.
        $router->match(['GET', 'POST'], '/install', [InstallController::class, 'index']);
        $router->get('/', [HomeController::class, 'index']);
        $router->match(['GET', 'POST'], '/signup', [AuthController::class, 'signup']);
        $router->match(['GET', 'POST'], '/login', [AuthController::class, 'login']);
        $router->post('/logout', [AuthController::class, 'logout']);
        $router->match(['GET', 'POST'], '/activate', [AuthController::class, 'activate']);
        $router->get('/dashboard', [DashboardController::class, 'index']);
        $router->get('/reservations', [ReservationController::class, 'index']);
        $router->match(['GET', 'POST'], '/reservations/create', [ReservationController::class, 'create']);
        $router->post('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
        $router->get('/messages/inbox', [MessageController::class, 'inbox']);
        $router->get('/messages/sent', [MessageController::class, 'sent']);
        $router->match(['GET', 'POST'], '/messages/compose', [MessageController::class, 'compose']);
        $router->get('/admin', [AdminController::class, 'index']);
        $router->get('/admin/users', [AdminController::class, 'users']);
        $router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser']);
        $router->post('/admin/users/{id}/reset-password', [AdminController::class, 'resetPassword']);
        $router->get('/admin/reservations', [AdminController::class, 'reservations']);
        $router->post('/admin/reservations/{id}/cancel', [AdminController::class, 'cancelReservation']);
        $router->match(['GET', 'POST'], '/admin/settings', [AdminController::class, 'settings']);
        $router->get('/admin/updates', [AdminController::class, 'updates']);
        $router->post('/admin/updates/check', [AdminController::class, 'checkUpdates']);
        $router->post('/admin/updates/install', [AdminController::class, 'installUpdate']);
        $router->post('/admin/updates/rollback', [AdminController::class, 'rollbackUpdate']);
        $router->get('/lang/{locale}', [HomeController::class, 'switchLanguage']);
    }
}
