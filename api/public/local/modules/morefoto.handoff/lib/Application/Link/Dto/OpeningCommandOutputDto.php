<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class OpeningCommandOutputDto
{
    public function __construct(
        public int $revision,
        public string $status,
        public string $openAt,
    ) {}
}
