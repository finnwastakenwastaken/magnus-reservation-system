<?php

declare(strict_types=1);

namespace App\Core;

final class ErrorHandler
{
    public static function handle(\Throwable $exception): Response
    {
        $status = $exception instanceof HttpException ? $exception->getStatusCode() : 500;

        if ($status === 403) {
            $translator = Container::get('translator');
            Flash::add('warning', $translator->get('errors.403_redirect'));
            return Response::redirect('/');
        }

        if (Container::get('config')['app']['debug']) {
            $message = $exception->getMessage() . "\n\n" . $exception->getTraceAsString();
            return new Response(nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')), $status);
        }

        $view = in_array($status, [403, 404, 419], true) ? (string) $status : '500';

        return Response::view('errors/' . $view, [
            'exception' => $exception,
        ], $status);
    }
}
