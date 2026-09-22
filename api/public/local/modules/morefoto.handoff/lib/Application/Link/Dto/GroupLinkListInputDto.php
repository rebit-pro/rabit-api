<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class GroupLinkListInputDto
{
    public function __construct(
        public ?string $institutionId,
        public ?string $shootId,
        public ?string $state,
        public int $page,
        public int $pageSize,
    ) {}
}
