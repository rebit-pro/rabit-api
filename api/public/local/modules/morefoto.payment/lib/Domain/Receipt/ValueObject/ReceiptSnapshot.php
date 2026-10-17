<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\ValueObject;

/** Неизменяемое содержимое чека: адрес покупателя и позиции; итог равен сумме позиций. */
final readonly class ReceiptSnapshot
{
    /** @param non-empty-list<ReceiptItem> $items */
    public function __construct(
        public string $email,
        public array $items,
    ) {}

    public function total(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->total();
        }

        return $total;
    }
}
