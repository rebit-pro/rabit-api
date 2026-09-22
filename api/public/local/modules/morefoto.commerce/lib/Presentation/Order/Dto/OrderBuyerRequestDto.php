<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

final readonly class OrderBuyerRequestDto
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public bool $reviewed,
        public string $comment = '',
        public ?string $receiptChannel = null,
    ) {}
}
