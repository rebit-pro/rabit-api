<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\ValueObject;

use Morefoto\Files\Domain\Delivery\Enum\DeliveryKindEnum;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;

/** Письмо со ссылкой на оплаченный заказ: кому, когда и какой операцией H1 оно отправляется. */
final readonly class FileDelivery
{
    /**
     * @param int         $orderId     внутренний ID заказа
     * @param null|string $operationId операция H1 после постановки в очередь
     */
    public function __construct(
        public string $publicId,
        public int $orderId,
        public DeliveryKindEnum $kind,
        public DeliveryStatusEnum $status,
        public string $recipient,
        public ?string $operationId,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $queuedAt,
    ) {}
}
