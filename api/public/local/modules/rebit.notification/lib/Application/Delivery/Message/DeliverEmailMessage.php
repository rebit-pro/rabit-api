<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\Message;

use Rebit\Share\Application\Contract\Messenger\AbstractMessage;

final readonly class DeliverEmailMessage extends AbstractMessage
{
    public function __construct(public string $operationId)
    {
        parent::__construct();
    }

    public function getDeduplicationKey(): string
    {
        return hash('sha256', self::class . ':' . $this->operationId);
    }
}
