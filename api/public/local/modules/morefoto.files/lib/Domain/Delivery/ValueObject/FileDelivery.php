<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\ValueObject;

use Morefoto\Files\Domain\Delivery\Enum\DeliveryKindEnum;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;

/** Отправка оригиналов и ссылки на оплаченный заказ: кому, когда, сколькими письмами и какой операцией H1 начата. */
final readonly class FileDelivery
{
    /**
     * @param int              $orderId     внутренний ID заказа
     * @param null|string      $operationId операция H1 первого письма после постановки всех писем в очередь
     * @param null|int         $letters     число писем по сохранённому плану; null — план ещё не записан
     * @param list<LetterFile> $linkOnly    кадры выше предела письма: их называют письма, файл — по ссылке
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
        public ?int $letters = null,
        public array $linkOnly = [],
    ) {}
}
