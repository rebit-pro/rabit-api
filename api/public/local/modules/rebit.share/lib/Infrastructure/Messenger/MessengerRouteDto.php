<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Rebit\Share\Application\Contract\Messenger\AbstractMessage;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;

final readonly class MessengerRouteDto
{
    /**
     * @param class-string<AbstractMessage> $messageClass
     * @param class-string                  $handlerClass
     */
    public function __construct(
        public string $messageClass,
        public string $handlerClass,
        public MessengerQueueEnum $queue,
    ) {}
}
