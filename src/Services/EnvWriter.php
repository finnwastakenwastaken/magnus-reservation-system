<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Writes the project `.env` file for installer and future operational tooling.
 *
 * Only example/default values and installer-provided values are written here;
 * runtime secrets must never be committed to version control.
 */
final class EnvWriter
{
    public function write(array $values): void
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $stringValue = (string) $value;
            if ($stringValue === '' || preg_match('/\s/', $stringValue) === 1) {
                $stringValue = '"' . addcslashes($stringValue, "\"\\") . '"';
            }
            $lines[] = $key . '=' . $stringValue;
        }

        $content = implode(PHP_EOL, $lines) . PHP_EOL;
        if (@file_put_contents(app_env_path(), $content) === false) {
            throw new \RuntimeException('Unable to write the .env file. Check file permissions.');
        }
    }
}
