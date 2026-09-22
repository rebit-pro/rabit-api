<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Morefoto\Commerce\Application\Order\Dto\OrderOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

/** @phpstan-import-type OrderQuote from OrderOutputDto */
final readonly class StaffOrderResultDto implements ResultDtoInterface
{
    /** @param OrderQuote $quote */
    public function __construct(
        public string $id,
        public string $number,
        public string $institutionId,
        public string $institutionName,
        public string $shootId,
        public string $shootName,
        public string $groupId,
        public string $groupName,
        public string $audience,
        public string $createdAt,
        public OrderBuyerResultDto $buyer,
        public array $quote,
        public string $paymentStatus,
        public string $productionStatus,
        public string $version,
    ) {}
}
