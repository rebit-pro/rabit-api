<?php

declare(strict_types=1);

use Morefoto\Production\Presentation\Command\RunProductionCommand;

return [
    'services' => ['value' => array_merge(require __DIR__ . '/di/archive.php', require __DIR__ . '/di/http.php'), 'readonly' => true],
    'console' => ['value' => ['commands' => [RunProductionCommand::class]], 'readonly' => true],
];
