<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class WorksheetLineResultDto implements ResultDtoInterface
{
    /** @param list<WorksheetFileResultDto> $files */
    public function __construct(
        public string $id,
        public string $kind,
        public string $columnKey,
        public string $productName,
        public string $format,
        public int $quantity,
        public int $printCount,
        public int $prints,
        public int $unitPrice,
        public int $total,
        public int $discount,
        public bool $coveredByGift,
        public bool $selected,
        public array $files,
    ) {}
}
