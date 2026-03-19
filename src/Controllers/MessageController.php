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
 * Resident conversation UI.
 *
 * The resident-facing message experience is thread-based rather than split into
 * disconnected inbox and sent lists.
 */
final class MessageController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $user = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $service = new MessageService();
        $summaries = $service->conversationSummariesForUser((int) $user['id'], $page, 10);
        $firstThreadUserId = isset($summaries['items'][0]['other_user_id']) ? (int) $summaries['items'][0]['other_user_id'] : null;

        return $this->view('messages/index', $summaries + [
            'page' => $page,
            'perPage' => 10,
            'activeThreadUserId' => $firstThreadUserId,
        ]);
    }

    public function inbox(Request $request, array $params = []): Response
    {
        Auth::requireUser();

        return $this->redirect('/messages');
    }

    public function sent(Request $request, array $params = []): Response
    {
        Auth::requireUser();

        return $this->redirect('/messages');
    }

    public function thread(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $translator = \App\Core\Container::get('translator');
        $user = Auth::user();
        $service = new MessageService();
        $page = max(1, (int) $request->input('page', 1));
        $threadUserId = (int) $params['userId'];
        $summaries = $service->conversationSummariesForUser((int) $user['id'], $page, 10);
        try {
            $thread = $service->threadForUser((int) $user['id'], $threadUserId);
        } catch (\Throwable) {
            Flash::add('danger', $translator->get('messages.thread_not_found'));
            return $this->redirect('/messages');
        }
        $turnstile = new TurnstileService();

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            if (!(new RateLimiter())->hit('message_reply', (string) $user['id'], 15, 3600)) {
                Flash::add('danger', $translator->get('validation.rate_limit'));
                return $this->redirect('/messages/thread/' . $threadUserId);
            }
            if (!$turnstile->verify((string) $request->input('cf-turnstile-response'), (string) $request->server('REMOTE_ADDR'))) {
                Flash::add('danger', $translator->get('validation.turnstile'));
                return $this->view('messages/thread', [
                    'items' => $summaries['items'],
                    'total' => $summaries['total'],
                    'page' => $page,
                    'perPage' => 10,
                    'thread' => $thread,
                    'activeThreadUserId' => $threadUserId,
                    'errors' => [],
                    'old' => $request->all(),
                ]);
            }

            try {
                $service->replyInThread(
                    $user,
                    $threadUserId,
                    (string) $request->input('subject', $thread['subject']),
                    (string) $request->input('body'),
                    $translator->locale()
                );
                Flash::add('success', $translator->get('messages.sent_success'));
                return $this->redirect('/messages/thread/' . $threadUserId);
            } catch (ValidationException $exception) {
                return $this->view('messages/thread', [
                    'items' => $summaries['items'],
                    'total' => $summaries['total'],
                    'page' => $page,
                    'perPage' => 10,
                    'thread' => $thread,
                    'activeThreadUserId' => $threadUserId,
                    'errors' => $exception->errors(),
                    'old' => $request->all(),
                ]);
            }
        }

        return $this->view('messages/thread', [
            'items' => $summaries['items'],
            'total' => $summaries['total'],
            'page' => $page,
            'perPage' => 10,
            'thread' => $thread,
            'activeThreadUserId' => $threadUserId,
            'errors' => [],
            'old' => ['subject' => $thread['subject']],
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
                return $this->redirect('/messages/thread/' . (int) $request->input('recipient_user_id'));
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
