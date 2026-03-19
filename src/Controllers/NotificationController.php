<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\NotificationService;

/**
 * Resident notification center.
 *
 * In-app notifications are the guaranteed fallback channel when email is not
 * configured, so users need a dedicated screen to review and acknowledge them.
 */
final class NotificationController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $page = max(1, (int) $request->input('page', 1));
        $service = new NotificationService();

        return $this->view('notifications/index', $service->paginatedForUser((int) Auth::user()['id'], $page, 15) + [
            'page' => $page,
            'perPage' => 15,
        ]);
    }

    public function markRead(Request $request, array $params): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        (new NotificationService())->markRead((int) $params['id'], (int) Auth::user()['id']);

        return $this->redirect('/notifications');
    }

    public function markAllRead(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        (new NotificationService())->markAllRead((int) Auth::user()['id']);

        return $this->redirect('/notifications');
    }
}
