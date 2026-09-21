<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\File\Dto;

final readonly class PreviewContentOutputDto
{
    public function __construct(
        public string $content,
    ) {}
}
