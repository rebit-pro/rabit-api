<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class SalesTotalsOutputDto
{
    /**
     * @param int $children        дети разметки в группах области
     * @param int $orderedFamilies дети с заказом в любом статусе оплаты
     * @param int $paidFamilies    дети с оплаченным заказом
     * @param int $paidTotal       копейки
     */
    public function __construct(
        public int $children,
        public int $orderedFamilies,
        public int $paidFamilies,
        public int $paidOrders,
        public int $prints,
        public int $digitalFiles,
        public int $bundles,
        public int $paidTotal,
    ) {}
}
