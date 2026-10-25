<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;
use Morefoto\Commerce\Domain\Sales\ValueObject\SalesArea;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;

final readonly class SalesReportAreaOutputDto
{
    /** @param list<GroupDirectoryItemOutputDto> $groups группы уровня в области сотрудника, порядок справочника */
    public function __construct(
        public SalesReportLevelEnum $level,
        public SalesArea $area,
        public array $groups,
    ) {}
}
