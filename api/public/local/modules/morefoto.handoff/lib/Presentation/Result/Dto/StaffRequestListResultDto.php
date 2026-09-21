<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Result\Dto;

use Morefoto\Handoff\Application\Request\Dto\StaffRequestListOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

/** @phpstan-import-type StaffRequestScope from StaffRequestListOutputDto */
final readonly class StaffRequestListResultDto implements ResultDtoInterface
{
    /**
     * @param list<StaffRequestResultDto> $items
     * @param StaffRequestScope           $scope
     */
    public function __construct(public array $items, public array $scope) {}
}
