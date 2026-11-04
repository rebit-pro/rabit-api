<?php

declare(strict_types=1);

use Morefoto\Handoff\Presentation\Command\RunGalleryOpeningsCommand;

return [
    'services' => ['value' => array_merge(require __DIR__ . '/di/handoff.php', require __DIR__ . '/di/link.php'), 'readonly' => true],
    'console' => ['value' => ['commands' => [RunGalleryOpeningsCommand::class]], 'readonly' => true],
];
