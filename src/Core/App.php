<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\InstallController;
use App\Controllers\LegalController;
use App\Controllers\MessageController;
use App\Controllers\NotificationController;
use App\Controllers\ResidentController;
use App\Controllers\ReservationController;
use App\Services\PrivacyService;

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
            try {
                (new PrivacyService())->runRetentionCleanup();
            } catch (\Throwable) {
                // Retention cleanup should not block normal requests.
            }
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
        $router->get('/availability', [ReservationController::class, 'publicOverview']);
        $router->get('/availability/feed', [ReservationController::class, 'publicFeed']);
        $router->match(['GET', 'POST'], '/signup', [AuthController::class, 'signup']);
        $router->match(['GET', 'POST'], '/login', [AuthController::class, 'login']);
        $router->post('/logout', [AuthController::class, 'logout']);
        $router->match(['GET', 'POST'], '/activate', [AuthController::class, 'activate']);
        $router->get('/dashboard', [DashboardController::class, 'index']);
        $router->get('/account', [AccountController::class, 'index']);
        $router->post('/account/profile', [AccountController::class, 'updateProfile']);
        $router->post('/account/email-change', [AccountController::class, 'requestEmailChange']);
        $router->get('/account/email-change/confirm', [AccountController::class, 'confirmEmailChange']);
        $router->post('/account/password', [AccountController::class, 'changePassword']);
        $router->post('/account/profile-picture', [AccountController::class, 'uploadProfilePicture']);
        $router->post('/account/profile-picture/remove', [AccountController::class, 'removeProfilePicture']);
        $router->get('/account/export', [AccountController::class, 'export']);
        $router->post('/account/delete', [AccountController::class, 'delete']);
        $router->get('/notifications', [NotificationController::class, 'index']);
        $router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
        $router->post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        $router->get('/residents', [ResidentController::class, 'index']);
        $router->get('/reservations', [ReservationController::class, 'index']);
        $router->get('/reservations/feed', [ReservationController::class, 'feed']);
        $router->match(['GET', 'POST'], '/reservations/create', [ReservationController::class, 'create']);
        $router->post('/reservations/quick-create', [ReservationController::class, 'quickCreate']);
        $router->post('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
        $router->post('/reservations/{id}/cancel-quick', [ReservationController::class, 'cancelQuick']);
        $router->get('/messages/inbox', [MessageController::class, 'inbox']);
        $router->get('/messages/sent', [MessageController::class, 'sent']);
        $router->match(['GET', 'POST'], '/messages/compose', [MessageController::class, 'compose']);
        $router->get('/admin', [AdminController::class, 'index']);
        $router->get('/admin/users', [AdminController::class, 'users']);
        $router->post('/admin/users/{id}/role', [AdminController::class, 'updateRole']);
        $router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser']);
        $router->post('/admin/users/{id}/reset-password', [AdminController::class, 'resetPassword']);
        $router->post('/admin/users/{id}/apartment', [AdminController::class, 'updateApartment']);
        $router->get('/admin/reservations', [AdminController::class, 'reservations']);
        $router->match(['GET', 'POST'], '/admin/reservations/{id}/edit', [AdminController::class, 'editReservation']);
        $router->post('/admin/reservations/{id}/cancel', [AdminController::class, 'cancelReservation']);
        $router->get('/admin/messages', [AdminController::class, 'messages']);
        $router->get('/admin/roles', [AdminController::class, 'roles']);
        $router->match(['GET', 'POST'], '/admin/roles/create', [AdminController::class, 'createRole']);
        $router->match(['GET', 'POST'], '/admin/roles/{id}/edit', [AdminController::class, 'editRole']);
        $router->post('/admin/roles/{id}/delete', [AdminController::class, 'deleteRole']);
        $router->match(['GET', 'POST'], '/admin/settings', [AdminController::class, 'settings']);
        $router->get('/admin/branding', [AdminController::class, 'branding']);
        $router->post('/admin/branding/logo', [AdminController::class, 'uploadLogo']);
        $router->post('/admin/branding/logo/reset', [AdminController::class, 'resetLogo']);
        $router->get('/admin/updates', [AdminController::class, 'updates']);
        $router->post('/admin/updates/check', [AdminController::class, 'checkUpdates']);
        $router->post('/admin/updates/install', [AdminController::class, 'installUpdate']);
        $router->post('/admin/updates/rollback', [AdminController::class, 'rollbackUpdate']);
        $router->get('/privacy-policy', [LegalController::class, 'privacy']);
        $router->get('/cookie-notice', [LegalController::class, 'cookies']);
        $router->get('/house-rules', [LegalController::class, 'houseRules']);
        $router->get('/lang/{locale}', [HomeController::class, 'switchLanguage']);
    }
}
