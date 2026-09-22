<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class StaffOrderListRequestDto implements RequestDtoInterface
{
    public function __construct(
        public ?string $q = null,
        public ?string $institutionId = null,
        public ?string $shootId = null,
        public ?string $groupId = null,
        public ?string $paymentStatus = null,
        public ?string $productionStatus = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $late = null,
        public ?string $settlement = null,
        public int $page = 1,
        public int $pageSize = 50,
    ) {}
}
