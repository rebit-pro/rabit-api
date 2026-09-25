<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Notification\Infrastructure\Max\MaxBotApiClient;
use Rebit\Notification\Infrastructure\Max\MaxSendOutcomeClassifier;
use Rebit\Share\Application\Contract\Notification\MaxBotAdminInterface;
use Rebit\Share\Application\Contract\Notification\MaxChatMessengerInterface;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

return [
    MaxBotApiClient::class => [
        'constructor' => static fn(): MaxBotApiClient => new MaxBotApiClient(
            Log::channel(LogChannelEnum::notification),
            new MaxSendOutcomeClassifier(),
            (string)(getenv('REBIT_NOTIFICATION_MAX_BOT_TOKEN') ?: ''),
            (string)(getenv('REBIT_NOTIFICATION_MAX_API_URL') ?: 'https://platform-api2.max.ru'),
            (string)(getenv('REBIT_NOTIFICATION_MAX_CA_FILE') ?: dirname(__DIR__) . '/resources/max/russian_trusted_root_ca.pem'),
        ),
    ],
    MaxChatMessengerInterface::class => [
        'constructor' => static fn(): MaxChatMessengerInterface => ServiceLocator::getInstance()->get(MaxBotApiClient::class),
    ],
    MaxBotAdminInterface::class => [
        'constructor' => static fn(): MaxBotAdminInterface => ServiceLocator::getInstance()->get(MaxBotApiClient::class),
    ],
];
