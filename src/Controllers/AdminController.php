<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditService;
use App\Services\ReservationService;
use App\Services\SettingsService;
use App\Services\UpdateService;
use App\Services\UserService;

/**
 * Administrative control surface.
 *
 * Every action in this controller requires an authenticated administrator and
 * is server-side validated again even if the admin UI hides actions visually.
 */
final class AdminController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();
        $userService = new UserService();
        $reservationService = new ReservationService();

        return $this->view('admin/index', [
            'pendingUsers' => $userService->paginatedUsers(1, 5, null, 0)['items'],
            'reservations' => $reservationService->paginatedAll(1, 5)['items'],
            'appVersion' => \App\Core\Container::get('config')['app']['version'],
        ]);
    }

    public function users(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();
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
        ]);
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

    public function reservations(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();
        $page = max(1, (int) $request->input('page', 1));
        $data = (new ReservationService())->paginatedAll($page, 15);

        return $this->view('admin/reservations', $data + [
            'page' => $page,
            'perPage' => 15,
        ]);
    }

    public function cancelReservation(Request $request, array $params): Response
    {
        Auth::requireAdmin();
        Validator::requireCsrf($request);
        (new ReservationService())->cancel((int) $params['id'], Auth::user(), true);
        Flash::add('success', \App\Core\Container::get('translator')->get('reservation.cancelled'));

        return $this->redirect('/admin/reservations');
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
