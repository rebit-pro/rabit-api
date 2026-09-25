<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class PaymentPageOutputDto
{
    /** @param list<PaymentItemOutputDto> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pageSize,
    ) {}
}
