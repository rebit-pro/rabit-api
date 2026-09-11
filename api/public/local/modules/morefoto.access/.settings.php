<?php

declare(strict_types=1);

use Morefoto\Access\Presentation\Console\BootstrapOrganizerCommand;

return [
    'services' => ['value' => require __DIR__ . '/di/access.php', 'readonly' => true],
    'console' => ['value' => ['commands' => [BootstrapOrganizerCommand::class]], 'readonly' => true],
];
