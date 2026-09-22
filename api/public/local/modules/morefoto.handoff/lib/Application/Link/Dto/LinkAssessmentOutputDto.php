<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

use Morefoto\Handoff\Domain\Link\ValueObject\LinkReadiness;

final readonly class LinkAssessmentOutputDto
{
    public function __construct(
        public LinkReadiness $readiness,
        public int $photoCount,
        public int $childCount,
    ) {}
}
