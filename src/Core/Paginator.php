<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public static function resolve(int $page, int $perPage): array
    {
        return [
            'page' => max(1, $page),
            'per_page' => $perPage,
            'offset' => (max(1, $page) - 1) * $perPage,
        ];
    }
}
