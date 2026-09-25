<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;
use Rebit\Share\Infrastructure\Controller\Attribute\SkipWhenNull;

final readonly class ConditionsSavedResultDto implements ResultDtoInterface
{
    public function __construct(
        public int $revision,
        #[SkipWhenNull]
        public ?int $conditionsRevision = null,
    ) {}
}
