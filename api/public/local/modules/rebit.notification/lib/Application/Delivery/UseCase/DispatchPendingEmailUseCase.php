<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\UseCase;

use Psr\Log\LoggerInterface;
use Rebit\Notification\Application\Delivery\Contract\DeliveryOperationRepositoryInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationClockInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationPublisherInterface;

final readonly class DispatchPendingEmailUseCase
{
    private const int PROCESSING_LEASE_SECONDS = 300;

    public function __construct(
        private DeliveryOperationRepositoryInterface $operations,
        private NotificationPublisherInterface $publisher,
        private NotificationClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $limit, bool $includeUnknown = false): int
    {
        if (1 > $limit || 500 < $limit) {
            throw new \InvalidArgumentException('Invalid notification dispatch limit.');
        }
        $now = $this->clock->now();
        $this->operations->recoverStale(
            $now->modify('-' . self::PROCESSING_LEASE_SECONDS . ' seconds'),
            $now,
        );
        if ($includeUnknown) {
            $this->operations->recoverUnknown($limit, $now);
        }
        $published = 0;
        foreach ($this->operations->dueOperationIds($limit, $now) as $operationId) {
            try {
                $this->publisher->publish($operationId);
                ++$published;
            } catch (\Throwable $error) {
                $this->logger->error('Notification operation remains pending after recovery publish failure.', [
                    'operationId' => $operationId,
                    'exception' => $error::class,
                ]);
            }
        }

        return $published;
    }
}
