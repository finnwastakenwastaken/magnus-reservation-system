<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;

/**
 * Cloudflare Turnstile verification wrapper.
 *
 * Turnstile is optional in local development. When keys are absent the service
 * returns success so developers are not blocked by missing anti-bot config.
 */
final class TurnstileService
{
    public function enabled(): bool
    {
        return (bool) Container::get('config')['turnstile']['enabled'];
    }

    public function siteKey(): string
    {
        return (string) Container::get('config')['turnstile']['site_key'];
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        if (!$token) {
            return false;
        }

        $payload = http_build_query([
            'secret' => Container::get('config')['turnstile']['secret_key'],
            'response' => $token,
            'remoteip' => $remoteIp,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        if ($response === false) {
            return false;
        }

        $decoded = json_decode($response, true);
        return (bool) ($decoded['success'] ?? false);
    }
}
