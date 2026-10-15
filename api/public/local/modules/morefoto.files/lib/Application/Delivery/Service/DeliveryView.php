<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Service;

use Morefoto\Files\Application\Delivery\Dto\DeliveryOutputDto;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\ValueObject\FileDelivery;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;

/**
 * Показывает покупателю судьбу письма по операции H1: «передано почтовому серверу» — это accepted, а не прочтение;
 * всё, что ещё в очереди или на повторе, — pending.
 */
final readonly class DeliveryView
{
    public function __construct(private EmailNotificationInterface $notifications) {}

    public function view(FileDelivery $delivery): DeliveryOutputDto
    {
        $status = 'pending';
        $acceptedAt = null;
        if (DeliveryStatusEnum::CANCELLED === $delivery->status) {
            $status = 'cancelled';
        } elseif (null !== $delivery->operationId) {
            $operation = $this->notifications->status($delivery->operationId);
            $status = match ($operation?->status) {
                'accepted' => 'accepted',
                'failed' => 'failed',
                default => 'pending',
            };
            $acceptedAt = null === $operation?->acceptedAt ? null : new \DateTimeImmutable($operation->acceptedAt, new \DateTimeZone('UTC'));
        }

        return new DeliveryOutputDto(
            id: $delivery->publicId,
            kind: $delivery->kind->value,
            status: $status,
            recipient: $delivery->recipient,
            createdAt: $delivery->createdAt,
            acceptedAt: $acceptedAt,
        );
    }
}
