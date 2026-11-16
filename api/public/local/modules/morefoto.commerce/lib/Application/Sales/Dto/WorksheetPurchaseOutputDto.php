<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class WorksheetPurchaseOutputDto
{
    public function __construct(
        public string $orderId,
        public string $number,
        public string $groupId,
        public string $groupName,
        public string $institutionName,
        public string $shootName,
        public int $shootId,
        public int $childId,
        public string $childPublicId,
        public string $childCode,
        public ?string $firstName,
        public ?string $lastName,
        public string $paymentStatus,
        public bool $latePayment,
        public bool $paid,
        public int $orderTotal,
        public string $gifts,
        public string $lineId,
        public string $kind,
        public string $productId,
        public string $productName,
        public string $format,
        public ?string $photoId,
        public int $quantity,
        public int $printCount,
        public int $unitPrice,
        public int $discount,
        public int $total,
        public bool $coveredByGift,
    ) {}
}
