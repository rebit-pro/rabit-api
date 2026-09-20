<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Messenger;

use Rebit\Notification\Application\Delivery\Message\DeliverEmailMessage;
use Rebit\Notification\Application\Delivery\Message\Handler\DeliverEmailMessageHandler;
use Rebit\Share\Infrastructure\Messenger\AbstractMessengerFactory;
use Rebit\Share\Infrastructure\Messenger\MessengerRouteDto;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;

final class NotificationMessengerFactory extends AbstractMessengerFactory
{
    protected static function routes(): array
    {
        return [
            new MessengerRouteDto(
                messageClass: DeliverEmailMessage::class,
                handlerClass: DeliverEmailMessageHandler::class,
                queue: MessengerQueueEnum::NOTIFICATION_EMAIL,
            ),
        ];
    }
}
