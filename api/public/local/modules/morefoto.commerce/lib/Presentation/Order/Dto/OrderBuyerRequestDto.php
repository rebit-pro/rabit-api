<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

final readonly class OrderBuyerRequestDto
{
    /**
     * @param string $name  E8: not stored; still accepted so a page opened before the release and a recovered attempt work
     * @param string $phone E8: not stored; accepted for the same reason
     */
    public function __construct(
        public string $email,
        public bool $reviewed,
        public string $comment = '',
        public ?string $receiptChannel = null,
        public string $name = '',
        public string $phone = '',
    ) {}
}
