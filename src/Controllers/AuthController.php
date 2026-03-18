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
use App\Services\RateLimiter;
use App\Services\TurnstileService;
use App\Services\UserService;

/**
 * Handles signup, login, logout, and mailbox activation flows.
 *
 * Security-sensitive behavior such as rate limiting, CSRF checks, Turnstile
 * validation, and session regeneration is deliberately kept close to the HTTP
 * entry points.
 */
final class AuthController extends Controller
{
    /**
     * Register a resident account and generate a mailbox activation code.
     *
     * The actual activation code is only surfaced in debug mode; production
     * installs are expected to deliver it physically to the resident.
     */
    public function signup(Request $request, array $params = []): Response
    {
        $translator = \App\Core\Container::get('translator');
        $turnstile = new TurnstileService();

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            if (!(new RateLimiter())->hit('signup', (string) ($request->server('REMOTE_ADDR') ?? 'unknown'), 10, 3600)) {
                Flash::add('danger', $translator->get('validation.rate_limit'));
                return $this->redirect('/signup');
            }

            if (!$turnstile->verify((string) $request->input('cf-turnstile-response'), (string) $request->server('REMOTE_ADDR'))) {
                Flash::add('danger', $translator->get('validation.turnstile'));
                return $this->view('auth/signup', ['old' => $request->all(), 'errors' => []]);
            }

            try {
                $activationCode = (new UserService())->createUser($request->all(), $translator->locale());
                $messageKey = \App\Core\Container::get('config')['app']['debug'] ? 'auth.signup_success_debug' : 'auth.signup_success';
                Flash::add('success', $translator->get($messageKey, ['code' => $activationCode]));
                return $this->redirect('/activate');
            } catch (ValidationException $exception) {
                return $this->view('auth/signup', ['old' => $request->all(), 'errors' => $exception->errors()]);
            }
        }

        return $this->view('auth/signup', ['old' => [], 'errors' => []]);
    }

    /**
     * Authenticate an already activated account.
     *
     * Login intentionally fails with a generic message so the UI does not reveal
     * whether a given email exists or is merely inactive.
     */
    public function login(Request $request, array $params = []): Response
    {
        $translator = \App\Core\Container::get('translator');
        $turnstile = new TurnstileService();

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            $email = strtolower(trim((string) $request->input('email')));
            if (!(new RateLimiter())->hit('login', $email . '|' . ($request->server('REMOTE_ADDR') ?? 'unknown'), 8, 900)) {
                Flash::add('danger', $translator->get('validation.rate_limit'));
                return $this->redirect('/login');
            }

            if (!$turnstile->verify((string) $request->input('cf-turnstile-response'), (string) $request->server('REMOTE_ADDR'))) {
                Flash::add('danger', $translator->get('validation.turnstile'));
                return $this->view('auth/login', ['old' => ['email' => $email], 'errors' => []]);
            }

            $user = (new UserService())->authenticate($email, (string) $request->input('password'));
            if (!$user) {
                Flash::add('danger', $translator->get('auth.login_failed'));
                return $this->view('auth/login', ['old' => ['email' => $email], 'errors' => []]);
            }

            Auth::login($user);
            Flash::add('success', $translator->get('auth.login_success'));
            return $this->redirect('/dashboard');
        }

        return $this->view('auth/login', ['old' => [], 'errors' => []]);
    }

    public function logout(Request $request, array $params = []): Response
    {
        Validator::requireCsrf($request);
        Auth::logout();

        return $this->redirect('/');
    }

    /**
     * Activate a resident account using the mailbox-delivered code.
     */
    public function activate(Request $request, array $params = []): Response
    {
        $translator = \App\Core\Container::get('translator');
        $turnstile = new TurnstileService();

        if ($request->method() === 'POST') {
            Validator::requireCsrf($request);
            $email = strtolower(trim((string) $request->input('email')));
            if (!(new RateLimiter())->hit('activate', $email . '|' . ($request->server('REMOTE_ADDR') ?? 'unknown'), 10, 3600)) {
                Flash::add('danger', $translator->get('validation.rate_limit'));
                return $this->redirect('/activate');
            }

            if (!$turnstile->verify((string) $request->input('cf-turnstile-response'), (string) $request->server('REMOTE_ADDR'))) {
                Flash::add('danger', $translator->get('validation.turnstile'));
                return $this->view('auth/activate', ['old' => ['email' => $email], 'errors' => []]);
            }

            $success = (new UserService())->activate($email, trim((string) $request->input('activation_code')));
            if (!$success) {
                Flash::add('danger', $translator->get('auth.activation_failed'));
                return $this->view('auth/activate', ['old' => ['email' => $email], 'errors' => []]);
            }

            Flash::add('success', $translator->get('auth.activation_success'));
            return $this->redirect('/login');
        }

        return $this->view('auth/activate', ['old' => [], 'errors' => []]);
    }
}
