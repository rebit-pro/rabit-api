<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Messenger;

use Morefoto\Support\Application\Question\Message\DeliverQuestionMessage;
use Morefoto\Support\Application\Question\Message\Handler\DeliverQuestionMessageHandler;
use Rebit\Share\Infrastructure\Messenger\AbstractMessengerFactory;
use Rebit\Share\Infrastructure\Messenger\MessengerRouteDto;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;

final class SupportMessengerFactory extends AbstractMessengerFactory
{
    protected static function routes(): array
    {
        return [
            new MessengerRouteDto(
                messageClass: DeliverQuestionMessage::class,
                handlerClass: DeliverQuestionMessageHandler::class,
                queue: MessengerQueueEnum::SUPPORT_MAX,
            ),
        ];
    }
}
