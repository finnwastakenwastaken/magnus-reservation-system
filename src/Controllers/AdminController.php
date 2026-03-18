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
use App\Services\AuditService;
use App\Services\BrandingService;
use App\Services\MessageService;
use App\Services\ReservationService;
use App\Services\SettingsService;
use App\Services\UpdateService;
use App\Services\UserService;

/**
 * Staff control surface.
 *
 * Admins retain all existing system privileges. Managers get a narrower
 * operational scope focused on user visibility, message oversight, and
 * reservation oversight without access to installer/updater/settings/branding
 * controls.
 */
final class AdminController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireRoles(['admin', 'manager']);
        $userService = new UserService();
        $reservationService = new ReservationService();

        return $this->view('admin/index', [
            'pendingUsers' => $userService->paginatedUsers(1, 5, null, 0)['items'],
            'reservations' => $reservationService->paginatedAll(1, 5)['items'],
            'appVersion' => \App\Core\Container::get('config')['app']['version'],
            'canManageSystem' => Auth::isAdmin(),
        ]);
    }

    public function users(Request $request, array $params = []): Response
    {
        Auth::requireRoles(['admin', 'manager']);
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
            'canEditUsers' => Auth::isAdmin(),
        ]);
    }

    public function updateRole(Request $request, array $params): Response
    {
        Auth::requireAdmin();
        Validator::requireCsrf($request);

        try {
            (new UserService())->updateRole((int) $params['id'], (string) $request->input('role'), (int) Auth::user()['id']);
            Flash::add('success', \App\Core\Container::get('translator')->get('admin.role_updated'));
        } catch (ValidationException $exception) {
            Flash::add('danger', \App\Core\Container::get('translator')->get($exception->errors()['role'] ?? 'validation.role_invalid'));
        }

        return $this->redirect('/admin/users');
    }

    public function deleteUser(Request $request, array $params): Response
    {
        Auth::requireAdmin();
        Validator::requireCsrf($request);
        (new UserService())->deleteUser((int) $params['id'], (int) Auth::user()['id']);
        Flash::add('success', \App\Core\Container::get('translator')->get('admin.user_deleted'));

        return $this->redirect('/admin/users');
    }

    public function resetPassword(Request $request, array $params): Response
    {
        Auth::requireAdmin();
        Validator::requireCsrf($request);
        $tempPassword = (new UserService())->adminResetPassword((int) $params['id'], (int) Auth::user()['id']);
        Flash::add('warning', \App\Core\Container::get('translator')->get('admin.password_reset_done', ['password' => $tempPassword]));

        return $this->redirect('/admin/users');
    }

    public function updateApartment(Request $request, array $params): Response
    {
        Auth::requireAdmin();
        Validator::requireCsrf($request);

        try {
            (new UserService())->adminUpdateApartment((int) $params['id'], (string) $request->input('apartment_number'), (int) Auth::user()['id']);
            Flash::add('success', \App\Core\Container::get('translator')->get('admin.apartment_updated'));
        } catch (\Throwable $exception) {
            Flash::add('danger', $exception instanceof ValidationException
                ? \App\Core\Container::get('translator')->get($exception->errors()['apartment_number'] ?? 'validation.apartment_invalid')
                : $exception->getMessage());
        }

        return $this->redirect('/admin/users');
    }

    public function reservations(Request $request, array $params = []): Response
    {
        Auth::requireRoles(['admin', 'manager']);
        $page = max(1, (int) $request->input('page', 1));
        $data = (new ReservationService())->paginatedAll($page, 15);

        return $this->view('admin/reservations', $data + [
            'page' => $page,
            'perPage' => 15,
        ]);
    }

    public function editReservation(Request $request, array $params): Response
    {
        Auth::requireRoles(['admin', 'manager']);
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
        Auth::requireRoles(['admin', 'manager']);
        Validator::requireCsrf($request);
        (new ReservationService())->cancelByStaff((int) $params['id'], Auth::user(), \App\Core\Container::get('translator')->locale());
        Flash::add('success', \App\Core\Container::get('translator')->get('reservation.cancelled'));

        return $this->redirect('/admin/reservations');
    }

    public function messages(Request $request, array $params = []): Response
    {
        Auth::requireRoles(['admin', 'manager']);
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
        Auth::requireAdmin();
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
        Auth::requireAdmin();

        return $this->view('admin/branding', [
            'logoPath' => (new BrandingService())->currentLogoPath(),
            'errors' => [],
        ]);
    }

    public function uploadLogo(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();
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
        Auth::requireAdmin();
        Validator::requireCsrf($request);
        (new BrandingService())->resetLogo((int) Auth::user()['id']);
        Flash::add('success', \App\Core\Container::get('translator')->get('admin.logo_reset'));

        return $this->redirect('/admin/branding');
    }

    public function updates(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();

        return $this->view('admin/updates', [
            'update' => (new UpdateService())->status(),
        ]);
    }

    public function checkUpdates(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();
        Validator::requireCsrf($request);
        Flash::add('info', \App\Core\Container::get('translator')->get('admin.updates_checked'));

        return $this->redirect('/admin/updates');
    }

    public function installUpdate(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();
        Validator::requireCsrf($request);

        try {
            (new UpdateService())->installUpdate(Auth::user());
            Flash::add('success', \App\Core\Container::get('translator')->get('admin.update_installed'));
        } catch (\Throwable $exception) {
            Flash::add('danger', $exception->getMessage());
        }

        return $this->redirect('/admin/updates');
    }

    public function rollbackUpdate(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();
        Validator::requireCsrf($request);

        try {
            (new UpdateService())->rollbackLatest(Auth::user());
            Flash::add('warning', \App\Core\Container::get('translator')->get('admin.update_rolled_back'));
        } catch (\Throwable $exception) {
            Flash::add('danger', $exception->getMessage());
        }

        return $this->redirect('/admin/updates');
    }
}
