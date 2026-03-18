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
use App\Services\MessageService;
use App\Services\RateLimiter;
use App\Services\TurnstileService;
use App\Services\UserService;

/**
 * Internal resident messaging UI.
 *
 * Email addresses are never exposed in the compose flow; users choose from
 * active residents and the service layer handles notification delivery.
 */
final class MessageController extends Controller
{
    public function inbox(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $user = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $service = new MessageService();

        return $this->view('messages/inbox', $service->inbox((int) $user['id'], $page, 10) + [
            'page' => $page,
            'perPage' => 10,
        ]);
    }

    public function sent(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $user = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $service = new MessageService();

        return $this->view('messages/sent', $service->sent((int) $user['id'], $page, 10) + [
            'page' => $page,
            'perPage' => 10,
        ]);
    }

    public function compose(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $translator = \App\Core\Container::get('translator');
        $user = Auth::user();
        $users = (new UserService())->activeRecipients((int) $user['id']);
        $turnstile = new TurnstileService();

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            if (!(new RateLimiter())->hit('message', (string) $user['id'], 10, 3600)) {
                Flash::add('danger', $translator->get('validation.rate_limit'));
                return $this->redirect('/messages/compose');
            }
            if (!$turnstile->verify((string) $request->input('cf-turnstile-response'), (string) $request->server('REMOTE_ADDR'))) {
                Flash::add('danger', $translator->get('validation.turnstile'));
                return $this->view('messages/compose', [
                    'users' => $users,
                    'old' => $request->all(),
                    'errors' => [],
                ]);
            }

            try {
                (new MessageService())->send(
                    $user,
                    (int) $request->input('recipient_user_id'),
                    (string) $request->input('subject'),
                    (string) $request->input('body'),
                    $translator->locale()
                );
                Flash::add('success', $translator->get('messages.sent_success'));
                return $this->redirect('/messages/sent');
            } catch (ValidationException $exception) {
                return $this->view('messages/compose', [
                    'users' => $users,
                    'old' => $request->all(),
                    'errors' => $exception->errors(),
                ]);
            }
        }

        return $this->view('messages/compose', [
            'users' => $users,
            'old' => ['recipient_user_id' => (string) $request->input('recipient_user_id', '')],
            'errors' => [],
        ]);
    }
}
