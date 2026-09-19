<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class ListPhotosInputDto
{
    public function __construct(
        public ?string $groupId,
        public int $page,
        public int $pageSize,
        public bool $noMatch,
    ) {}
}
