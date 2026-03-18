<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\InstallerService;

/**
 * First-run installation wizard controller.
 *
 * The installer is intentionally isolated from the rest of the application so
 * a fresh project can be configured before a database or admin account exists.
 */
final class InstallController extends Controller
{
    public function index(Request $request, array $params = []): Response
    {
        $config = \App\Core\Container::get('config');
        if (app_is_installed($config)) {
            return new Response('Not Found', 404);
        }

        if ($request->method() === 'POST') {
            if (!Csrf::validate((string) $request->input('_csrf'))) {
                return $this->view('install/index', [
                    'old' => $request->all(),
                    'errors' => ['general_key' => 'errors.419'],
                ]);
            }

            try {
                (new InstallerService())->install($request->all());
                return $this->view('install/success', [
                    'appUrl' => rtrim((string) $request->input('app_url'), '/'),
                ]);
            } catch (ValidationException $exception) {
                return $this->view('install/index', [
                    'old' => $request->all(),
                    'errors' => $exception->errors(),
                ]);
            } catch (\Throwable $exception) {
                return $this->view('install/index', [
                    'old' => $request->all(),
                    'errors' => ['general_text' => $exception->getMessage()],
                ]);
            }
        }

        return $this->view('install/index', [
            'old' => [
                'db_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'db_port' => $_ENV['DB_PORT'] ?? '3306',
                'db_database' => $_ENV['DB_DATABASE'] ?? 'living_room',
                'db_username' => $_ENV['DB_USERNAME'] ?? 'root',
                'db_password' => $_ENV['DB_PASSWORD'] ?? '',
                'app_url' => $_ENV['APP_URL'] ?? $this->guessUrl(),
            ],
            'errors' => [],
        ]);
    }

    private function guessUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }
}
