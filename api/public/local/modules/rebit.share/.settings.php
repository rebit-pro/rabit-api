<?php

declare(strict_types=1);

use Rebit\Share\Presentation\Command\Audit\AuditConsumerCommand;
use Rebit\Share\Presentation\Command\Audit\TestAuditCommand;

return [
    'services' => [
        'value' => array_merge(
            require __DIR__ . '/di/Layers/Infrastructure.php',
            require __DIR__ . '/di/Layers/Messenger.php',
            require __DIR__ . '/di/audit.php',
            require __DIR__ . '/di/file.php',
            require __DIR__ . '/di/telegram.php',
        ),
        'readonly' => true,
    ],
    'console' => [
        'value' => [
            'commands' => [
                AuditConsumerCommand::class,
                TestAuditCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];
