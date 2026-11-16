<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class WorksheetColumnOutputDto
{
    public function __construct(
        public string $key,
        public string $name,
        public string $format,
    ) {}
}
