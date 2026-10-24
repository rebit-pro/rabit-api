<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class ChildSalesResultDto implements ResultDtoInterface
{
    /**
     * @param string $status    paid | late | unpaid | none
     * @param int    $paidTotal копейки
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $status,
        public int $orders,
        public int $paidOrders,
        public int $prints,
        public int $digitalFiles,
        public int $bundles,
        public int $paidTotal,
        public bool $hasPreview,
    ) {}
}
