<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class GroupLinkListResultDto implements ResultDtoInterface
{
    /** @param list<GroupLinkSummaryResultDto> $items */
    public function __construct(
        public array $items,
    ) {}
}
