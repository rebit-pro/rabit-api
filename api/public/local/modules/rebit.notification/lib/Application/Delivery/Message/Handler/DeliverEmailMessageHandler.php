<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\Message\Handler;

use Rebit\Notification\Application\Delivery\Message\DeliverEmailMessage;
use Rebit\Notification\Application\Delivery\UseCase\DeliverEmailUseCase;

final readonly class DeliverEmailMessageHandler
{
    public function __construct(private DeliverEmailUseCase $deliver) {}

    public function __invoke(DeliverEmailMessage $message): void
    {
        $this->deliver->execute($message->operationId);
    }
}
