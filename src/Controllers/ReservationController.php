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
use App\Services\ReservationService;

/**
 * Resident reservation screens.
 *
 * Controllers stay thin: business rules live in ReservationService so the
 * overlap, quota, and allowed-hours logic is enforced consistently.
 */
final class ReservationController extends Controller
{
    public function publicOverview(Request $request, array $params = []): Response
    {
        $selectedMonth = $this->selectedMonthFromInput($request->input('month'));
        $service = new ReservationService();

        return $this->view('reservations/public', [
            'selectedMonth' => $selectedMonth,
            'reservations' => $service->publicCalendarMonth($selectedMonth),
        ]);
    }

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $selectedMonth = $this->selectedMonthFromInput($request->input('month'));
        $service = new ReservationService();

        return $this->view('reservations/index', [
            'selectedMonth' => $selectedMonth,
            'reservations' => $service->calendarMonth($selectedMonth),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $translator = \App\Core\Container::get('translator');
        $user = Auth::user();
        $service = new ReservationService();

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);

            try {
                $service->create(
                    $user,
                    (string) $request->input('start_datetime'),
                    (string) $request->input('end_datetime')
                );
                Flash::add('success', $translator->get('reservation.created'));
                return $this->redirect('/reservations');
            } catch (ValidationException $exception) {
                return $this->view('reservations/create', [
                    'old' => $request->all(),
                    'errors' => $exception->errors(),
                ]);
            }
        }

        return $this->view('reservations/create', ['old' => [], 'errors' => []]);
    }

    public function cancel(Request $request, array $params): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        (new ReservationService())->cancel((int) $params['id'], Auth::user());
        Flash::add('success', \App\Core\Container::get('translator')->get('reservation.cancelled'));

        return $this->redirect('/reservations');
    }

    /**
     * Normalize the month picker input.
     *
     * The UI submits `YYYY-MM`. Invalid values fall back to the current month
     * instead of bubbling an exception into a 500 response.
     */
    private function selectedMonthFromInput(mixed $monthInput): \DateTimeImmutable
    {
        if (!is_string($monthInput) || preg_match('/^\d{4}-\d{2}$/', $monthInput) !== 1) {
            return new \DateTimeImmutable('first day of this month');
        }

        try {
            return new \DateTimeImmutable($monthInput . '-01');
        } catch (\Exception) {
            return new \DateTimeImmutable('first day of this month');
        }
    }
}
