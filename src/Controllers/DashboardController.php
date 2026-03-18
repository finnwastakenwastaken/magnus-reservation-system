<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\ReservationService;

/**
 * Authenticated resident dashboard.
 */
final class DashboardController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $user = Auth::user();
        $reservationService = new ReservationService();
        $messageService = new MessageService();
        $notificationService = new NotificationService();

        return $this->view('dashboard', [
            'user' => $user,
            'upcomingReservations' => $reservationService->userUpcoming((int) $user['id']),
            'inbox' => $messageService->inbox((int) $user['id'], 1, 5)['items'],
            'notifications' => $notificationService->recentForUser((int) $user['id'], 5),
        ]);
    }
}
