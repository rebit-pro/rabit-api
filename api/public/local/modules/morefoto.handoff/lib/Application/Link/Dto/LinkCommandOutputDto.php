<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;
use Rebit\Share\Contracts\Access\Dto\LinkActorOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;

final readonly class LinkCommandOutputDto
{
    /** @param null|array<string, bool|int|string> $replay stored result of the same idempotent request */
    public function __construct(
        public LinkActorOutputDto $actor,
        public GroupDirectoryItemOutputDto $group,
        public LinkState $state,
        public LinkAssessmentOutputDto $assessment,
        public string $resource,
        public string $payloadHash,
        public ?array $replay,
    ) {}
}
