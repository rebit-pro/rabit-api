<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Staff\Result\Dto;

use Morefoto\Access\Application\Staff\Dto\StaffOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class StaffListResultDto implements ResultDtoInterface
{
    /** @param list<StaffOutputDto> $items */
    public function __construct(
        public array $items,
    ) {}
}
