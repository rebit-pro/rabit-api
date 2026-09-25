<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class GroupLinkCountersResultDto implements ResultDtoInterface
{
    /** @param array{preparing: int, open: int, closed: int} $byState */
    public function __construct(
        public string $referenceNow,
        public array $byState,
        public int $closingSoon,
        public int $prepared,
    ) {}
}
