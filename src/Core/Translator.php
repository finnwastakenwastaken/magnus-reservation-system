<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session/cookie backed translation lookup service.
 *
 * Templates use translation keys instead of duplicated markup so Dutch and
 * English remain in sync across public and admin pages.
 */
final class Translator
{
    private array $messages = [];

    public function locale(): string
    {
        $config = Container::get('config');
        $allowed = ['en', 'nl'];
        // Language switching is allowed through a query string so the installer
        // can be localized before the normal application routes are available.
        if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
            $_SESSION['locale'] = $_GET['lang'];
            setcookie('locale', $_GET['lang'], time() + 31536000, '/');
        }
        $locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? $config['app']['locale'];

        return in_array($locale, $allowed, true) ? $locale : $config['app']['fallback_locale'];
    }

    public function get(string $key, array $replace = []): string
    {
        $locale = $this->locale();
        if (!isset($this->messages[$locale])) {
            $this->messages[$locale] = require BASE_PATH . '/src/Lang/' . $locale . '.php';
        }

        $message = $this->messages[$locale][$key] ?? $key;
        foreach ($replace as $name => $value) {
            $message = str_replace(':' . $name, (string) $value, $message);
        }

        return $message;
    }
}
