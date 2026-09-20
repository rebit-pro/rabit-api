<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class SetCoverInputDto
{
    public function __construct(
        public int $revision,
        public string $photoId,
    ) {}
}
