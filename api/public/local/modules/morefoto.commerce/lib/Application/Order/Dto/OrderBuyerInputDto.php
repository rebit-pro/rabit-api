<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class OrderBuyerInputDto
{
    /**
     * @param string $name  E8: ignored by the order; kept only so the replay hash of an attempt begun before E8 stays the same
     * @param string $phone E8: ignored by the order; kept for the replay hash too
     */
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $comment,
        public ?string $receiptChannel,
        public bool $reviewed,
    ) {}
}
