<?php

declare(strict_types=1);

use Morefoto\Access\Presentation\Console\BootstrapOrganizerCommand;

return [
    'services' => ['value' => array_merge(require __DIR__ . '/di/access.php', require __DIR__ . '/di/avatar.php', require __DIR__ . '/di/catalog.php'), 'readonly' => true],
    'console' => ['value' => ['commands' => [BootstrapOrganizerCommand::class]], 'readonly' => true],
];
