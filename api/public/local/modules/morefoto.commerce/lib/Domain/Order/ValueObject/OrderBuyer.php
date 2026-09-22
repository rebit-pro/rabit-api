<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\ValueObject;

/** Нормализованные контакты покупателя, прошедшие предметную проверку. */
final readonly class OrderBuyer
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $comment,
        public ?string $receiptChannel,
    ) {}
}
