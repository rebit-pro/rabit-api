<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Rebit\Share\Application\Contract\Messenger\AbstractMessage;
use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerMessagePublisher implements MessagePublisherInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private DedupCacheInterface $dedupCache,
    ) {}

    public function dispatch(AbstractMessage $message, int $deduplicateTime = 0): void
    {
        if (0 < $deduplicateTime) {
            $dedupKey = 'msg_dedup_' . $message->getDeduplicationKey();

            if (!$this->dedupCache->claim($dedupKey, $deduplicateTime)) {
                return;
            }
        }

        try {
            $this->bus->dispatch($message);
        } catch (\Throwable $exception) {
            if (0 < $deduplicateTime) {
                $this->dedupCache->release($dedupKey);
            }

            throw $exception;
        }
    }
}
