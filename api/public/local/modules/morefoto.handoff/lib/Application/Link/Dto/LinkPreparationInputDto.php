<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class LinkPreparationInputDto
{
    public function __construct(
        public int $revision,
        public string $signature,
    ) {}
}
