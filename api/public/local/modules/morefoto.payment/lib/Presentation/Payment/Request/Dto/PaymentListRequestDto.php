<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class PaymentListRequestDto implements RequestDtoInterface
{
    public function __construct(
        public ?string $status = null,
        public ?string $orderNumber = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $late = null,
        public int $page = 1,
        public int $pageSize = 50,
    ) {}
}
