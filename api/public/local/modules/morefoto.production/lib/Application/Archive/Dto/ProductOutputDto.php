<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Dto;

final readonly class ProductOutputDto
{
    public function __construct(
        public string $name,
        public string $format,
        public int $files,
        public int $prints,
    ) {}
}
