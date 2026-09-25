<?php

declare(strict_types=1);

use Rebit\Notification\Presentation\Command\DispatchPendingNotificationsCommand;
use Rebit\Notification\Presentation\Command\NotificationConsumerCommand;

return [
    'services' => [
        'value' => array_merge(
            require __DIR__ . '/di/lead.php',
            require __DIR__ . '/di/delivery.php',
            require __DIR__ . '/di/max.php',
        ),
        'readonly' => true,
    ],
    'console' => [
        'value' => [
            'commands' => [
                NotificationConsumerCommand::class,
                DispatchPendingNotificationsCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];
