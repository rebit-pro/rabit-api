<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Audit\Messenger;

use Rebit\Share\Application\Audit\Port\AuditPublisherInterface;
use Rebit\Share\Application\Contract\Messenger\AbstractMessage;
use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;

final readonly class AuditPublisher implements AuditPublisherInterface
{
    public function __construct(
        private MessagePublisherInterface $publisher,
    ) {}

    public function dispatch(AbstractMessage $message, int $deduplicateTime = 0): void
    {
        $this->publisher->dispatch($message, $deduplicateTime);
    }
}
