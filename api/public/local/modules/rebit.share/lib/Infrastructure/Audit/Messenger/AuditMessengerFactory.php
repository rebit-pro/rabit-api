<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Audit\Messenger;

use Rebit\Share\Application\Audit\Message\AuditMessage;
use Rebit\Share\Application\Audit\Message\Handler\AuditMessageHandler;
use Rebit\Share\Infrastructure\Messenger\AbstractMessengerFactory;
use Rebit\Share\Infrastructure\Messenger\MessengerRouteDto;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;

final class AuditMessengerFactory extends AbstractMessengerFactory
{
    /**
     * @return list<MessengerRouteDto>
     */
    protected static function routes(): array
    {
        return [
            new MessengerRouteDto(
                messageClass: AuditMessage::class,
                handlerClass: AuditMessageHandler::class,
                queue: MessengerQueueEnum::AUDIT,
            ),
        ];
    }
}
