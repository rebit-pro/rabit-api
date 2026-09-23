<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Institution\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class InstitutionSummaryResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $availability,
        public string $reason,
    ) {}
}
