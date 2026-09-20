<?php

declare(strict_types=1);

use Morefoto\Media\Presentation\Command\DispatchPendingMediaCommand;
use Morefoto\Media\Presentation\Command\MediaConsumerCommand;

return [
    'services' => [
        'value' => require __DIR__ . '/di/media.php',
        'readonly' => true,
    ],
    'console' => [
        'value' => [
            'commands' => [
                MediaConsumerCommand::class,
                DispatchPendingMediaCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];
