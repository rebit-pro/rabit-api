<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Morefoto\Commerce\Application\Order\Dto\OrderOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

/** @phpstan-import-type OrderQuote from OrderOutputDto */
final readonly class BuyerOrderResultDto implements ResultDtoInterface
{
    /** @param OrderQuote $quote */
    public function __construct(
        public string $id,
        public string $number,
        public string $groupId,
        public string $institutionName,
        public string $shootName,
        public string $groupName,
        public string $audience,
        public OrderBuyerResultDto $buyer,
        public array $quote,
        public string $paymentStatus,
        public ?string $paidAt,
        public bool $latePayment,
        public string $productionStatus,
        public string $version,
        public OrderPeriodResultDto $period,
        public string $accessKeyExpiresAt,
        public string $createdAt,
    ) {}
}
