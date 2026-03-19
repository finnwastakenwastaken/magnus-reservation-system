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
        $service = new ReservationService();

        return $this->view('reservations/public', [
            'calendarRules' => $service->rules(),
        ]);
    }

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $service = new ReservationService();
        $user = Auth::user();

        return $this->view('reservations/index', [
            'user' => $user,
            'calendarRules' => $service->rules(),
            'upcomingReservations' => $service->userUpcoming((int) $user['id']),
        ]);
    }

    public function feed(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $range = $this->rangeFromRequest($request);
        $events = (new ReservationService())->calendarFeed(Auth::user(), $range['start'], $range['end']);

        return new Response(
            json_encode(['events' => $events], JSON_THROW_ON_ERROR),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    public function publicFeed(Request $request, array $params = []): Response
    {
        $range = $this->rangeFromRequest($request);
        $events = (new ReservationService())->publicFeed($range['start'], $range['end']);

        return new Response(
            json_encode(['events' => $events], JSON_THROW_ON_ERROR),
            200,
            ['Content-Type' => 'application/json']
        );
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

    public function quickCreate(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        $translator = \App\Core\Container::get('translator');

        try {
            (new ReservationService())->create(
                Auth::user(),
                (string) $request->input('start_datetime'),
                (string) $request->input('end_datetime')
            );

            return new Response(
                json_encode(['ok' => true], JSON_THROW_ON_ERROR),
                200,
                ['Content-Type' => 'application/json']
            );
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messageKey) {
                $errors[$field] = $translator->get($messageKey);
            }

            return new Response(
                json_encode(['ok' => false, 'errors' => $errors], JSON_THROW_ON_ERROR),
                422,
                ['Content-Type' => 'application/json']
            );
        }
    }

    public function cancel(Request $request, array $params): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        (new ReservationService())->cancel((int) $params['id'], Auth::user());
        Flash::add('success', \App\Core\Container::get('translator')->get('reservation.cancelled'));

        return $this->redirect('/reservations');
    }

    public function cancelQuick(Request $request, array $params): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        (new ReservationService())->cancel((int) $params['id'], Auth::user());

        return new Response(
            json_encode(['ok' => true], JSON_THROW_ON_ERROR),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    private function rangeFromRequest(Request $request): array
    {
        try {
            $start = new \DateTimeImmutable((string) $request->input('start'));
            $end = new \DateTimeImmutable((string) $request->input('end'));
        } catch (\Throwable) {
            $start = new \DateTimeImmutable('first day of this month');
            $end = $start->modify('+1 month');
        }

        return ['start' => $start, 'end' => $end];
    }
}
