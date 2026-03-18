<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private readonly string $content,
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {
    }

    public static function view(string $view, array $data = [], int $status = 200): self
    {
        $content = Container::get('view')->render($view, $data);
        return new self($content, $status);
    }

    public static function redirect(string $path): self
    {
        return new self('', 302, ['Location' => $path]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->content;
    }
}
