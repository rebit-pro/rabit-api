<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\UseCase;

use Psr\Log\LoggerInterface;
use Rebit\Notification\Application\Delivery\Contract\DeliveryOperationRepositoryInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationClockInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationPublisherInterface;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\NotificationOperationOutputDto;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;

final readonly class QueueEmailUseCase implements EmailNotificationInterface
{
    public function __construct(
        private DeliveryOperationRepositoryInterface $operations,
        private NotificationPublisherInterface $publisher,
        private NotificationClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function queue(EmailNotificationInputDto $input): NotificationOperationOutputDto
    {
        $payloadHash = hash('sha256', implode("\0", [
            'email',
            mb_strtolower($input->recipient),
            $input->subject,
            $input->body,
            (string)$input->maxAttempts,
        ]));
        $operation = $this->operations->createOrGet(
            id: $this->uuid(),
            input: $input,
            payloadHash: $payloadHash,
            now: $this->clock->now(),
        );
        if (in_array($operation->status, ['pending', 'retryWait'], true)) {
            try {
                $this->publisher->publish($operation->id);
            } catch (\Throwable $error) {
                $this->logger->error('Notification operation remains pending after publish failure.', [
                    'operationId' => $operation->id,
                    'exception' => $error::class,
                ]);
            }
        }

        return $operation->output();
    }

    public function status(string $operationId): ?NotificationOperationOutputDto
    {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $operationId)) {
            throw new \InvalidArgumentException('Invalid notification operation id.');
        }

        return $this->operations->find($operationId)?->output();
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
