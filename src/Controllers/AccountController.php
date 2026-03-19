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
use App\Services\PrivacyService;

/**
 * Resident self-service account and privacy controls.
 *
 * Residents may manage contact/privacy fields, email, and password, but cannot
 * change apartment assignment because residency data is admin-managed.
 */
final class AccountController extends Controller
{
    /**
     * Render the resident account/privacy control center.
     */
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $service = new PrivacyService();
        $user = Auth::user();

        return $this->view('account/index', [
            'user' => $user,
            'accountData' => $service->accountData((int) $user['id']),
            'errors' => [],
            'old' => [],
        ]);
    }

    /**
     * Persist resident-managed optional profile fields and visibility toggles.
     */
    public function updateProfile(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        $service = new PrivacyService();
        $user = Auth::user();
        $translator = \App\Core\Container::get('translator');

        try {
            $service->updateProfilePrivacy($user, $request->all());
            Flash::add('success', $translator->get('account.profile_saved'));
            return $this->redirect('/account');
        } catch (ValidationException $exception) {
            return $this->view('account/index', [
                'user' => $user,
                'accountData' => $service->accountData((int) $user['id']),
                'errors' => $exception->errors(),
                'old' => $request->all(),
            ]);
        }
    }

    /**
     * Start a verified email-change request without switching immediately.
     */
    public function requestEmailChange(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        $service = new PrivacyService();
        $user = Auth::user();
        $translator = \App\Core\Container::get('translator');

        try {
            $service->requestEmailChange(
                $user,
                (string) $request->input('new_email'),
                (string) $request->input('current_password_for_email'),
                $translator->locale()
            );
            Flash::add('success', $translator->get('account.email_change_requested'));
            return $this->redirect('/account');
        } catch (ValidationException $exception) {
            return $this->view('account/index', [
                'user' => $user,
                'accountData' => $service->accountData((int) $user['id']),
                'errors' => $exception->errors(),
                'old' => $request->all(),
            ]);
        }
    }

    /**
     * Complete an email-change request from the emailed confirmation token.
     */
    public function confirmEmailChange(Request $request, array $params = []): Response
    {
        $translator = \App\Core\Container::get('translator');
        $success = (new PrivacyService())->confirmEmailChange((string) $request->input('token'));
        Flash::add($success ? 'success' : 'danger', $translator->get($success ? 'account.email_change_confirmed' : 'account.email_change_invalid'));

        return $this->redirect('/login');
    }

    /**
     * Rotate the resident password after verifying the current password.
     */
    public function changePassword(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        $service = new PrivacyService();
        $user = Auth::user();
        $translator = \App\Core\Container::get('translator');

        try {
            $service->changePassword(
                $user,
                (string) $request->input('current_password'),
                (string) $request->input('new_password')
            );
            Flash::add('success', $translator->get('account.password_changed'));
            return $this->redirect('/account');
        } catch (ValidationException $exception) {
            return $this->view('account/index', [
                'user' => $user,
                'accountData' => $service->accountData((int) $user['id']),
                'errors' => $exception->errors(),
                'old' => $request->all(),
            ]);
        }
    }

    /**
     * Upload or replace the resident's profile picture.
     */
    public function uploadProfilePicture(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        $service = new PrivacyService();
        $user = Auth::user();
        $translator = \App\Core\Container::get('translator');

        try {
            $service->updateProfilePicture($user, $request->file('profile_picture'));
            Flash::add('success', $translator->get('account.profile_picture_saved'));
            return $this->redirect('/account');
        } catch (ValidationException $exception) {
            return $this->view('account/index', [
                'user' => $user,
                'accountData' => $service->accountData((int) $user['id']),
                'errors' => $exception->errors(),
                'old' => $request->all(),
            ]);
        }
    }

    /**
     * Remove the resident's uploaded profile picture and fall back to the
     * default avatar presentation.
     */
    public function removeProfilePicture(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        (new PrivacyService())->removeProfilePicture(Auth::user());
        Flash::add('success', \App\Core\Container::get('translator')->get('account.profile_picture_removed'));

        return $this->redirect('/account');
    }

    /**
     * Return a machine-readable export of the resident's own stored account data.
     */
    public function export(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        $user = Auth::user();
        (new AuditService())->log((int) $user['id'], 'user.data_exported', 'user', (string) $user['id']);
        $payload = json_encode((new PrivacyService())->accountData((int) $user['id']), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        return new Response($payload, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="account-data.json"',
        ]);
    }

    /**
     * Perform self-service account deletion with password confirmation.
     */
    public function delete(Request $request, array $params = []): Response
    {
        Auth::requireUser();
        Validator::requireCsrf($request);
        $translator = \App\Core\Container::get('translator');

        try {
            (new PrivacyService())->deleteOwnAccount(
                Auth::user(),
                (string) $request->input('delete_password'),
                (bool) $request->input('delete_confirm')
            );
            Flash::add('warning', $translator->get('account.deleted'));
            return $this->redirect('/');
        } catch (ValidationException $exception) {
            $service = new PrivacyService();
            $user = Auth::user();
            return $this->view('account/index', [
                'user' => $user,
                'accountData' => $service->accountData((int) $user['id']),
                'errors' => $exception->errors(),
                'old' => $request->all(),
            ]);
        }
    }
}
