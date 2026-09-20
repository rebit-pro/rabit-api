<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\UseCase;

use Rebit\Notification\Application\Delivery\Contract\DeliveryOperationRepositoryInterface;
use Rebit\Notification\Application\Delivery\Contract\EmailTransportInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationClockInterface;
use Rebit\Notification\Application\Delivery\Exception\DefiniteDeliveryException;

/**
 * Выполняет одну защищённую lease попытку доставки сохранённого email-уведомления.
 *
 * Фиксирует принятый, отклонённый или неизвестный исход и рассчитывает ограниченный повтор для определённой ошибки.
 */
final readonly class DeliverEmailUseCase
{
    private const int PROCESSING_LEASE_SECONDS = 300;
    private const int INITIAL_RETRY_SECONDS = 30;
    private const int MAX_RETRY_SECONDS = 3600;

    public function __construct(
        private DeliveryOperationRepositoryInterface $operations,
        private EmailTransportInterface $transport,
        private NotificationClockInterface $clock,
    ) {}

    public function execute(string $operationId): void
    {
        $now = $this->clock->now();
        $operation = $this->operations->startAttempt(
            $operationId,
            $now,
            $now->modify('-' . self::PROCESSING_LEASE_SECONDS . ' seconds'),
        );
        if (null === $operation) {
            return;
        }
        if ('email' !== $operation->channel) {
            $this->operations->markRejected($operation->id, $operation->attempts, 'unsupported_channel', null, $now);

            return;
        }
        try {
            $this->transport->send($operation);
            $this->operations->markAccepted($operation->id, $operation->attempts, $this->clock->now());
        } catch (DefiniteDeliveryException $error) {
            $nextAttemptAt = null;
            if ($operation->attempts < $operation->maxAttempts) {
                $delay = min(
                    self::INITIAL_RETRY_SECONDS * (2 ** ($operation->attempts - 1)),
                    self::MAX_RETRY_SECONDS,
                );
                $nextAttemptAt = $this->clock->now()->modify('+' . $delay . ' seconds');
            }
            $this->operations->markRejected(
                $operation->id,
                $operation->attempts,
                $error->errorCode,
                $nextAttemptAt,
                $this->clock->now(),
            );
        } catch (\Throwable $error) {
            $this->operations->markUnknown(
                $operation->id,
                $operation->attempts,
                'transport_outcome_unknown',
                $this->clock->now(),
            );
        }
    }
}
