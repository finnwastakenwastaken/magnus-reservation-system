<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PrivacyService;

/**
 * Privacy-safe resident directory used inside the platform.
 */
final class ResidentController extends Controller
{
    /**
     * Show a privacy-filtered resident directory for logged-in users.
     */
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();

        return $this->view('residents/index', [
            'residents' => (new PrivacyService())->visibleResidents((int) Auth::user()['id']),
        ]);
    }
}
