<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], int $status = 200): Response
    {
        return Response::view($view, $data, $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($path);
    }
}
