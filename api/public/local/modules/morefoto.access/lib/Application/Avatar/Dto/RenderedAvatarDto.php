<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Dto;

final readonly class RenderedAvatarDto
{
    public function __construct(
        public string $full,
        public string $thumb,
    ) {}
}
