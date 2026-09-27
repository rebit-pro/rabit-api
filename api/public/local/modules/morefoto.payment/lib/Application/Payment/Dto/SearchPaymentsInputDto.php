<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

use Morefoto\Payment\Domain\Payment\Enum\PaymentSortEnum;

final readonly class SearchPaymentsInputDto
{
    /** @param null|string $dateFrom YYYY-MM-DD по Москве */
    public function __construct(
        public ?string $status,
        public ?string $orderNumber,
        public ?string $dateFrom,
        public ?string $dateTo,
        public ?bool $latePayment,
        public int $page,
        public int $pageSize,
        public PaymentSortEnum $sort = PaymentSortEnum::CREATED_AT,
        public bool $descending = true,
    ) {}
}
