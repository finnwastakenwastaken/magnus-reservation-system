<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Template renderer with layout selection.
 *
 * The view layer stays intentionally thin: controllers prepare data, views only
 * render escaped output and reuse shared layouts/partials.
 */
final class View
{
    public function render(string $view, array $data = []): string
    {
        $translator = Container::get('translator');
        $config = Container::get('config');
        $flash = Flash::all();
        $auth = null;
        $siteSettings = [];
        if (app_is_installed($config)) {
            try {
                $auth = Auth::user();
            } catch (\Throwable) {
                $auth = null;
            }
            try {
                $siteSettings = (new \App\Services\SettingsService())->all();
            } catch (\Throwable) {
                $siteSettings = [];
            }
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/src/Views/' . $view . '.php';
        $content = (string) ob_get_clean();

        ob_start();
        $layout = str_starts_with($view, 'install/') ? 'install' : 'app';
        require BASE_PATH . '/src/Views/layouts/' . $layout . '.php';

        return (string) ob_get_clean();
    }
}
