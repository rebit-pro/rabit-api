<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class OrderReceiptLineOutputDto
{
    /**
     * @param string $kind      physical | digital | bundle
     * @param string $name      название продукции из снимка
     * @param int    $unitPrice цена единицы для покупателя в копейках (со скидкой сотрудника, без подарка)
     * @param int    $total     сумма строки в копейках; 0 — строка оплачена подарком
     */
    public function __construct(
        public string $kind,
        public string $name,
        public int $quantity,
        public int $unitPrice,
        public int $total,
    ) {}
}
