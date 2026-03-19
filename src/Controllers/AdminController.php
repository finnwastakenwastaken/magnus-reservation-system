<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Security\Permissions;
use App\Services\AuditService;
use App\Services\BrandingService;
use App\Services\MessageService;
use App\Services\ReservationService;
use App\Services\RoleService;
use App\Services\SettingsService;
use App\Services\UpdateService;
use App\Services\UserService;

/**
 * Staff control surface.
 *
 * Administrators can define custom staff roles, but all enforcement is still
 * handled server-side through granular permission checks. The protected admin
 * role keeps full access as a super-admin safeguard.
 */
final class AdminController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::ADMIN_ACCESS);
        $userService = new UserService();
        $reservationService = new ReservationService();

        return $this->view('admin/index', [
            'pendingUsers' => Auth::hasPermission(Permissions::USERS_VIEW) ? $userService->paginatedUsers(1, 5, null, 0)['items'] : [],
            'reservations' => Auth::hasPermission(Permissions::RESERVATIONS_VIEW_ALL) ? $reservationService->paginatedAll(1, 5)['items'] : [],
            'appVersion' => \App\Core\Container::get('config')['app']['version'],
            'canViewUsers' => Auth::hasPermission(Permissions::USERS_VIEW),
            'canViewReservations' => Auth::hasPermission(Permissions::RESERVATIONS_VIEW_ALL),
            'canViewMessages' => Auth::hasPermission(Permissions::MESSAGES_VIEW_PRIVATE),
            'canManageRoles' => Auth::hasPermission(Permissions::ROLES_MANAGE),
            'canManageSettings' => Auth::hasPermission(Permissions::SETTINGS_MANAGE),
            'canManageBranding' => Auth::hasPermission(Permissions::BRANDING_MANAGE),
            'canManageUpdates' => Auth::hasPermission(Permissions::UPDATES_MANAGE),
        ]);
    }

    public function users(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::USERS_VIEW);
        $page = max(1, (int) $request->input('page', 1));
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $isActive = $status === '' || $status === null ? null : (int) $status;
        $data = (new UserService())->paginatedUsers($page, 10, $search !== '' ? $search : null, $isActive);

        return $this->view('admin/users', [
            'items' => $data['items'],
            'total' => $data['total'],
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'perPage' => 10,
            'roles' => (new RoleService())->assignableRoles(),
            'canEditUsers' => Auth::hasPermission(Permissions::USERS_EDIT),
            'canAssignRoles' => Auth::hasPermission(Permissions::USERS_ASSIGN_ROLES),
            'canDeleteUsers' => Auth::hasPermission(Permissions::USERS_DELETE),
            'currentUserIsSuperAdmin' => (int) (Auth::user()['is_super_admin'] ?? 0) === 1,
        ]);
    }

    public function updateRole(Request $request, array $params): Response
    {
        Auth::requirePermission(Permissions::USERS_ASSIGN_ROLES);
        Validator::requireCsrf($request);

        try {
            (new UserService())->assignRole((int) $params['id'], (int) $request->input('role_id'), (int) Auth::user()['id']);
            Flash::add('success', \App\Core\Container::get('translator')->get('admin.user_role_updated'));
        } catch (ValidationException $exception) {
            Flash::add('danger', \App\Core\Container::get('translator')->get($exception->errors()['role_id'] ?? $exception->errors()['role'] ?? 'validation.role_invalid'));
        }

        return $this->redirect('/admin/users');
    }

    public function deleteUser(Request $request, array $params): Response
    {
        Auth::requirePermission(Permissions::USERS_DELETE);
        Validator::requireCsrf($request);
        $translator = \App\Core\Container::get('translator');

        try {
            (new UserService())->deleteUser((int) $params['id'], (int) Auth::user()['id']);
            Flash::add('success', $translator->get('admin.user_deleted'));
        } catch (ValidationException $exception) {
            Flash::add('danger', $translator->get($exception->errors()['role_id'] ?? 'validation.super_admin_protected'));
        }

        return $this->redirect('/admin/users');
    }

    public function resetPassword(Request $request, array $params): Response
    {
        Auth::requirePermission(Permissions::USERS_EDIT);
        Validator::requireCsrf($request);
        $translator = \App\Core\Container::get('translator');

        try {
            $tempPassword = (new UserService())->adminResetPassword((int) $params['id'], (int) Auth::user()['id']);
            Flash::add('warning', $translator->get('admin.password_reset_done', ['password' => $tempPassword]));
        } catch (ValidationException $exception) {
            Flash::add('danger', $translator->get($exception->errors()['role_id'] ?? 'validation.super_admin_protected'));
        }

        return $this->redirect('/admin/users');
    }

    public function updateApartment(Request $request, array $params): Response
    {
        Auth::requirePermission(Permissions::USERS_EDIT);
        Validator::requireCsrf($request);

        try {
            (new UserService())->adminUpdateApartment((int) $params['id'], (string) $request->input('apartment_number'), (int) Auth::user()['id']);
            Flash::add('success', \App\Core\Container::get('translator')->get('admin.apartment_updated'));
        } catch (\Throwable $exception) {
            Flash::add('danger', $exception instanceof ValidationException
                ? \App\Core\Container::get('translator')->get($exception->errors()['apartment_number'] ?? $exception->errors()['role_id'] ?? 'validation.apartment_invalid')
                : $exception->getMessage());
        }

        return $this->redirect('/admin/users');
    }

    public function reservations(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::RESERVATIONS_VIEW_ALL);
        $page = max(1, (int) $request->input('page', 1));
        $data = (new ReservationService())->paginatedAll($page, 15);

        return $this->view('admin/reservations', $data + [
            'page' => $page,
            'perPage' => 15,
            'canManageReservations' => Auth::hasPermission(Permissions::RESERVATIONS_MANAGE_ALL),
        ]);
    }

    public function editReservation(Request $request, array $params): Response
    {
        Auth::requirePermission(Permissions::RESERVATIONS_MANAGE_ALL);
        $service = new ReservationService();
        $translator = \App\Core\Container::get('translator');
        $reservation = $service->findDetailedById((int) $params['id']);
        if ($reservation === null) {
            Flash::add('danger', $translator->get('reservation.not_found'));
            return $this->redirect('/admin/reservations');
        }

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            try {
                $service->updateByStaff(
                    (int) $params['id'],
                    Auth::user(),
                    (string) $request->input('start_datetime'),
                    (string) $request->input('end_datetime'),
                    $translator->locale()
                );
                Flash::add('success', $translator->get('reservation.updated'));
                return $this->redirect('/admin/reservations');
            } catch (ValidationException $exception) {
                return $this->view('admin/reservation-edit', [
                    'reservation' => $reservation,
                    'old' => $request->all(),
                    'errors' => $exception->errors(),
                ]);
            }
        }

        return $this->view('admin/reservation-edit', [
            'reservation' => $reservation,
            'old' => [
                'start_datetime' => date('Y-m-d\TH:i', strtotime($reservation['start_datetime'])),
                'end_datetime' => date('Y-m-d\TH:i', strtotime($reservation['end_datetime'])),
            ],
            'errors' => [],
        ]);
    }

    public function cancelReservation(Request $request, array $params): Response
    {
        Auth::requirePermission(Permissions::RESERVATIONS_MANAGE_ALL);
        Validator::requireCsrf($request);
        (new ReservationService())->cancelByStaff((int) $params['id'], Auth::user(), \App\Core\Container::get('translator')->locale());
        Flash::add('success', \App\Core\Container::get('translator')->get('reservation.cancelled'));

        return $this->redirect('/admin/reservations');
    }

    public function messages(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::MESSAGES_VIEW_PRIVATE);
        $page = max(1, (int) $request->input('page', 1));
        $data = (new MessageService())->paginatedAll($page, 15);
        (new AuditService())->log((int) Auth::user()['id'], 'staff.messages_oversight_viewed', 'message', 'list', ['page' => $page]);

        return $this->view('admin/messages', $data + [
            'page' => $page,
            'perPage' => 15,
        ]);
    }

    public function settings(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::SETTINGS_MANAGE);
        $settings = new SettingsService();
        $translator = \App\Core\Container::get('translator');

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            $startHour = (int) $request->input('booking_start_hour');
            $endHour = (int) $request->input('booking_end_hour');
            $weekHours = (int) $request->input('max_hours_per_week');
            $monthHours = (int) $request->input('max_hours_per_month');

            if ($startHour < 0 || $startHour > 23 || $endHour < 1 || $endHour > 24 || $startHour >= $endHour || $weekHours < 1 || $monthHours < $weekHours) {
                Flash::add('danger', $translator->get('admin.settings_invalid'));
                return $this->redirect('/admin/settings');
            }

            $settings->updateMany([
                'booking_start_hour' => $startHour,
                'booking_end_hour' => $endHour,
                'max_hours_per_week' => $weekHours,
                'max_hours_per_month' => $monthHours,
            ]);
            (new AuditService())->log((int) Auth::user()['id'], 'admin.settings_updated', 'settings', 'booking');
            Flash::add('success', $translator->get('admin.settings_saved'));
            return $this->redirect('/admin/settings');
        }

        return $this->view('admin/settings', [
            'settings' => $settings->bookingRules(),
        ]);
    }

    public function branding(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::BRANDING_MANAGE);

        return $this->view('admin/branding', [
            'logoPath' => (new BrandingService())->currentLogoPath(),
            'errors' => [],
        ]);
    }

    public function uploadLogo(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::BRANDING_MANAGE);
        Validator::requireCsrf($request);
        $translator = \App\Core\Container::get('translator');
        $branding = new BrandingService();

        try {
            $branding->updateLogo($request->file('site_logo'), (int) Auth::user()['id']);
            Flash::add('success', $translator->get('admin.logo_updated'));
            return $this->redirect('/admin/branding');
        } catch (ValidationException $exception) {
            return $this->view('admin/branding', [
                'logoPath' => $branding->currentLogoPath(),
                'errors' => $exception->errors(),
            ]);
        }
    }

    public function resetLogo(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::BRANDING_MANAGE);
        Validator::requireCsrf($request);
        (new BrandingService())->resetLogo((int) Auth::user()['id']);
        Flash::add('success', \App\Core\Container::get('translator')->get('admin.logo_reset'));

        return $this->redirect('/admin/branding');
    }

    public function updates(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::UPDATES_MANAGE);

        return $this->view('admin/updates', [
            'update' => (new UpdateService())->status(),
        ]);
    }

    public function checkUpdates(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::UPDATES_MANAGE);
        Validator::requireCsrf($request);
        Flash::add('warning', \App\Core\Container::get('translator')->get('admin.updates_disabled'));

        return $this->redirect('/admin/updates');
    }

    public function installUpdate(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::UPDATES_MANAGE);
        Validator::requireCsrf($request);

        try {
            (new UpdateService())->installUpdate(Auth::user());
        } catch (\Throwable $exception) {
            Flash::add('danger', $exception->getMessage());
        }

        return $this->redirect('/admin/updates');
    }

    public function rollbackUpdate(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::UPDATES_MANAGE);
        Validator::requireCsrf($request);

        try {
            (new UpdateService())->rollbackLatest(Auth::user());
        } catch (\Throwable $exception) {
            Flash::add('danger', $exception->getMessage());
        }

        return $this->redirect('/admin/updates');
    }

    public function roles(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::ROLES_MANAGE);

        return $this->view('admin/roles', [
            'roles' => (new RoleService())->allWithCounts(),
            'currentUserIsSuperAdmin' => (int) (Auth::user()['is_super_admin'] ?? 0) === 1,
        ]);
    }

    public function createRole(Request $request, array $params = []): Response
    {
        Auth::requirePermission(Permissions::ROLES_MANAGE);
        $service = new RoleService();

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            try {
                $service->create($request->all(), (int) Auth::user()['id']);
                Flash::add('success', \App\Core\Container::get('translator')->get('admin.role_created'));
                return $this->redirect('/admin/roles');
            } catch (ValidationException $exception) {
                return $this->view('admin/role-form', [
                    'role' => null,
                    'permissions' => $service->permissionCatalog(),
                    'errors' => $exception->errors(),
                    'old' => $request->all(),
                ]);
            }
        }

        return $this->view('admin/role-form', [
            'role' => null,
            'permissions' => $service->permissionCatalog(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function editRole(Request $request, array $params): Response
    {
        Auth::requirePermission(Permissions::ROLES_MANAGE);
        $service = new RoleService();
        $role = $service->find((int) $params['id']);
        if ($role === null) {
            Flash::add('danger', \App\Core\Container::get('translator')->get('admin.role_not_found'));
            return $this->redirect('/admin/roles');
        }
        if ((int) ($role['is_super_admin'] ?? 0) === 1 && (int) (Auth::user()['is_super_admin'] ?? 0) !== 1) {
            Flash::add('danger', \App\Core\Container::get('translator')->get('validation.super_admin_protected'));
            return $this->redirect('/admin/roles');
        }

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            try {
                $service->update((int) $params['id'], $request->all(), (int) Auth::user()['id']);
                Flash::add('success', \App\Core\Container::get('translator')->get('admin.role_updated'));
                return $this->redirect('/admin/roles');
            } catch (ValidationException $exception) {
                return $this->view('admin/role-form', [
                    'role' => $role,
                    'permissions' => $service->permissionCatalog(),
                    'errors' => $exception->errors(),
                    'old' => $request->all(),
                ]);
            }
        }

        return $this->view('admin/role-form', [
            'role' => $role,
            'permissions' => $service->permissionCatalog(),
            'errors' => [],
            'old' => [
                'name' => $role['name'],
                'description' => $role['description'],
                'permissions' => $role['permissions'] ?? [],
            ],
        ]);
    }

    public function deleteRole(Request $request, array $params): Response
    {
        Auth::requirePermission(Permissions::ROLES_MANAGE);
        Validator::requireCsrf($request);

        try {
            (new RoleService())->delete((int) $params['id'], (int) Auth::user()['id']);
            Flash::add('success', \App\Core\Container::get('translator')->get('admin.role_deleted'));
        } catch (ValidationException $exception) {
            $errorKey = $exception->errors()['name'] ?? 'validation.role_invalid';
            Flash::add('danger', \App\Core\Container::get('translator')->get($errorKey));
        }

        return $this->redirect('/admin/roles');
    }
}
