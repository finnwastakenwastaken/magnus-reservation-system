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
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $month = $request->input('month');
        $selectedMonth = $month ? new \DateTimeImmutable((string) $month . '-01') : new \DateTimeImmutable('first day of this month');
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
}
