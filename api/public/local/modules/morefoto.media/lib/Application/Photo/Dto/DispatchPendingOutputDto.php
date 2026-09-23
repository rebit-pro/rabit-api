<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class DispatchPendingOutputDto
{
    public function __construct(
        public int $published,
        public int $failed,
    ) {}
}
