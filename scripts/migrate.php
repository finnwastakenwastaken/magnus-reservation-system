<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Container;
use App\Core\Database;
use App\Services\MigrationService;

Container::set('db', Database::connection());

$executed = (new MigrationService())->migrate();
if ($executed === []) {
    fwrite(STDOUT, "No pending migrations.\n");
    exit(0);
}

foreach ($executed as $migration) {
    fwrite(STDOUT, "Applied: {$migration}\n");
}
