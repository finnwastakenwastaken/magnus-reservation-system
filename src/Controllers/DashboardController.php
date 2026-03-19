<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Legacy dashboard route kept only as a compatibility redirect.
 */
final class DashboardController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireUser();

        return $this->redirect('/reservations');
    }
}
