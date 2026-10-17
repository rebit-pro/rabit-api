<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Service;

use Morefoto\Files\Application\Delivery\Dto\DeliveryOutputDto;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\ValueObject\FileDelivery;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;

/**
 * Показывает покупателю судьбу отправки по операциям H1 её писем (J5 T-08): «передано почтовому серверу» — это accepted
 * всех писем, а не прочтение; окончательный отказ хотя бы одного письма — failed; всё, что ещё в очереди или на повторе, —
 * pending.
 */
final readonly class DeliveryView
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveries,
        private EmailNotificationInterface $notifications,
    ) {}

    public function view(FileDelivery $delivery): DeliveryOutputDto
    {
        $parts = DeliveryStatusEnum::CANCELLED === $delivery->status ? [] : $this->deliveries->parts($delivery->publicId);
        $accepted = 0;
        $failed = false;
        $photos = 0;
        $acceptedAt = null;
        foreach ($parts as $part) {
            $photos += count($part->files);
            $operation = null === $part->operationId ? null : $this->notifications->status($part->operationId);
            if ('accepted' === $operation?->status) {
                ++$accepted;
                $at = null === $operation->acceptedAt ? null : new \DateTimeImmutable($operation->acceptedAt, new \DateTimeZone('UTC'));
                $acceptedAt = null === $acceptedAt || $at > $acceptedAt ? $at : $acceptedAt;
            } elseif ('failed' === $operation?->status) {
                $failed = true;
            }
        }
        $status = match (true) {
            DeliveryStatusEnum::CANCELLED === $delivery->status => 'cancelled',
            $failed => 'failed',
            [] !== $parts && count($parts) === $accepted => 'accepted',
            default => 'pending',
        };

        return new DeliveryOutputDto(
            id: $delivery->publicId,
            kind: $delivery->kind->value,
            status: $status,
            recipient: $delivery->recipient,
            createdAt: $delivery->createdAt,
            acceptedAt: 'accepted' === $status ? $acceptedAt : null,
            letters: count($parts),
            acceptedLetters: $accepted,
            photos: $photos,
            linkOnly: count($delivery->linkOnly),
        );
    }
}
