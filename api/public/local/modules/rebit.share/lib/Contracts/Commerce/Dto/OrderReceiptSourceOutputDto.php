<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class OrderReceiptSourceOutputDto
{
    /**
     * @param string                          $email адрес покупателя для чека (E8: обязателен)
     * @param list<OrderReceiptLineOutputDto> $lines строки снимка в порядке номера
     */
    public function __construct(
        public string $email,
        public array $lines,
    ) {}
}
