<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

use Morefoto\Commerce\Domain\Sales\Enum\ChildSalesStatusEnum;

final readonly class ChildSalesOutputDto
{
    /**
     * @param int         $paidTotal копейки
     * @param null|string $photoId   кадр миниатюры для PDF; null — миниатюры нет. Наружу не отдаётся.
     */
    public function __construct(
        public string $id,
        public string $code,
        public ChildSalesStatusEnum $status,
        public int $orders,
        public int $paidOrders,
        public int $prints,
        public int $digitalFiles,
        public int $bundles,
        public int $paidTotal,
        public ?string $photoId,
    ) {}
}
