<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Messenger;

use Morefoto\Files\Application\Files\Message\BuildArchiveMessage;
use Morefoto\Files\Application\Files\Message\Handler\BuildArchiveMessageHandler;
use Rebit\Share\Infrastructure\Messenger\AbstractMessengerFactory;
use Rebit\Share\Infrastructure\Messenger\MessengerRouteDto;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;

final class FilesMessengerFactory extends AbstractMessengerFactory
{
    protected static function routes(): array
    {
        return [
            new MessengerRouteDto(
                messageClass: BuildArchiveMessage::class,
                handlerClass: BuildArchiveMessageHandler::class,
                queue: MessengerQueueEnum::FILES_ARCHIVE,
            ),
        ];
    }
}
