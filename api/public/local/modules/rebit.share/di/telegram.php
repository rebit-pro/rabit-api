<?php

declare(strict_types=1);

use Rebit\Share\Infrastructure\Telegram\TelegramBotApiClient;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

// Env-имена исторически REBIT_NOTIFICATION_* (заведены до выноса клиента в share):
// токен приходит из docker-секрета rebit_telegram_bot_token, см. runtime-env.php.
return [
    TelegramBotApiClient::class => [
        'constructor' => static function(): TelegramBotApiClient {
            return new TelegramBotApiClient(
                Log::channel(LogChannelEnum::notification),
                (string)(getenv('REBIT_NOTIFICATION_TELEGRAM_BOT_TOKEN') ?: ''),
                (string)(getenv('REBIT_NOTIFICATION_TELEGRAM_API_URL') ?: 'https://api.telegram.org'),
                (string)(getenv('REBIT_NOTIFICATION_TELEGRAM_PROXY') ?: ''),
            );
        },
    ],
];
