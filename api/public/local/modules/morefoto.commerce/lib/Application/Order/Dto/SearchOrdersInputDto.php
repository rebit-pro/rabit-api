<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class SearchOrdersInputDto
{
    public function __construct(
        public ?string $query,
        public ?string $institutionId,
        public ?string $shootId,
        public ?string $groupId,
        public ?string $paymentStatus,
        public ?string $productionStatus,
        public ?string $dateFrom,
        public ?string $dateTo,
        public int $page,
        public int $pageSize,
    ) {}
}
