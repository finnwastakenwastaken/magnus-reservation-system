<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Public landing page and language switch handler.
 */
final class HomeController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        return $this->view('home');
    }

    public function switchLanguage(Request $request, array $params): Response
    {
        $_SESSION['locale'] = in_array($params['locale'], ['en', 'nl'], true) ? $params['locale'] : 'en';
        setcookie('locale', $_SESSION['locale'], time() + 31536000, '/');

        return $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
