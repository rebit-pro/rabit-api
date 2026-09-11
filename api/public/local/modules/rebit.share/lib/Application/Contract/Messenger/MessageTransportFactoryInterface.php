<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Messenger;

use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Порт создания транспорта очереди.
 */
interface MessageTransportFactoryInterface
{
    public function create(MessengerQueueEnum $queue): TransportInterface;
}
