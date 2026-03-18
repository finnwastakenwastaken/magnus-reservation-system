<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Static legal and transparency pages.
 */
final class LegalController extends Controller
{
    /**
     * Privacy policy describing the app's actual data handling behavior.
     */
    public function privacy(Request $request, array $params = []): Response
    {
        return $this->view('legal/privacy');
    }

    /**
     * Cookie notice for the session, locale, and optional Turnstile cookies.
     */
    public function cookies(Request $request, array $params = []): Response
    {
        return $this->view('legal/cookies');
    }

    /**
     * House rules for use of the shared room and internal messaging.
     */
    public function houseRules(Request $request, array $params = []): Response
    {
        return $this->view('legal/house-rules');
    }
}
