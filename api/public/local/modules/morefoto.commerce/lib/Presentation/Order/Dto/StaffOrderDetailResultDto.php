<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Morefoto\Commerce\Application\Order\Dto\OrderOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

/** @phpstan-import-type OrderQuote from OrderOutputDto */
final readonly class StaffOrderDetailResultDto implements ResultDtoInterface
{
    /**
     * @param OrderQuote                                                                                                                    $quote
     * @param list<array{childId: string, childCode: string, assignmentId: string, photoId: string, code: string, width: int, height: int}> $correctionPhotos
     */
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
        public ?string $paidAt,
        public bool $latePayment,
        public string $productionStatus,
        public string $version,
        public OrderPeriodResultDto $period,
        public array $correctionPhotos,
    ) {}
}
