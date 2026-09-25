<?php

declare(strict_types=1);

use Morefoto\Support\Presentation\Command\ConsumeQuestionMessagesCommand;
use Morefoto\Support\Presentation\Command\DispatchPendingQuestionMessagesCommand;
use Morefoto\Support\Presentation\Command\MaxStatusCommand;
use Morefoto\Support\Presentation\Command\MaxSubscribeCommand;

return [
    'services' => ['value' => require __DIR__ . '/di/support.php', 'readonly' => true],
    'console' => [
        'value' => [
            'commands' => [
                ConsumeQuestionMessagesCommand::class,
                DispatchPendingQuestionMessagesCommand::class,
                MaxStatusCommand::class,
                MaxSubscribeCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];
