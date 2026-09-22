<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class OrderBuyerOutputDto
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $comment,
        public ?string $receiptChannel,
    ) {}
}
