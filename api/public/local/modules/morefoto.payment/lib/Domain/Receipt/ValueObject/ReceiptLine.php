<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\ValueObject;

/** Строка неизменяемого снимка заказа, из которой строится позиция чека; суммы в копейках. */
final readonly class ReceiptLine
{
    /** @param bool $print печатная продукция; цифровые файлы и комплект — электронные */
    public function __construct(
        public bool $print,
        public string $name,
        public int $quantity,
        public int $unitPrice,
        public int $total,
    ) {}
}
