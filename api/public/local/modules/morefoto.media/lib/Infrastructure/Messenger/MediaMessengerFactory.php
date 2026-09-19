<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\Messenger;

use Morefoto\Media\Application\Photo\Message\Handler\ProcessPhotoMessageHandler;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Rebit\Share\Infrastructure\Messenger\AbstractMessengerFactory;
use Rebit\Share\Infrastructure\Messenger\MessengerRouteDto;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;

final class MediaMessengerFactory extends AbstractMessengerFactory
{
    protected static function routes(): array
    {
        return [
            new MessengerRouteDto(
                messageClass: ProcessPhotoMessage::class,
                handlerClass: ProcessPhotoMessageHandler::class,
                queue: MessengerQueueEnum::MEDIA_PROCESSING,
            ),
        ];
    }
}
