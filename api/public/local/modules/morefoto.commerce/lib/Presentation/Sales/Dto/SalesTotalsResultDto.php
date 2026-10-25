<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class SalesTotalsResultDto implements ResultDtoInterface
{
    /** @param int $paidTotal копейки */
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
