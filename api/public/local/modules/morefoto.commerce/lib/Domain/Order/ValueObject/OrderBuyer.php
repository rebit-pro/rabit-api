<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\ValueObject;

/** Нормализованные контакты покупателя, прошедшие предметную проверку; с E8 — только email. */
final readonly class OrderBuyer
{
    public function __construct(
        public string $email,
        public string $comment,
        public ?string $receiptChannel,
    ) {}
}
