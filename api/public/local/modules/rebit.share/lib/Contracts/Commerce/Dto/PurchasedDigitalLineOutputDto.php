<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class PurchasedDigitalLineOutputDto
{
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public string $lineId,
        public string $groupId,
        public int $shootId,
        public int $childId,
        public string $childCode,
        public string $kind,
        public ?string $photoId,
    ) {}
}
