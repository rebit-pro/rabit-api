<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Morefoto\Commerce\Application\Order\Dto\OrderOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

/** @phpstan-import-type OrderQuote from OrderOutputDto */
final readonly class CreatedOrderResultDto implements ResultDtoInterface
{
    /** @param OrderQuote $quote */
    public function __construct(
        public string $id,
        public string $number,
        public string $groupId,
        public string $accessKey,
        public string $accessKeyExpiresAt,
        public string $paymentStatus,
        public string $productionStatus,
        public array $quote,
        public OrderBuyerResultDto $buyer,
        public string $version,
        public string $createdAt,
    ) {}
}
