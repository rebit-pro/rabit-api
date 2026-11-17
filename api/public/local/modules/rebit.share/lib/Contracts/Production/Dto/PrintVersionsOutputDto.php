<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Production\Dto;

final readonly class PrintVersionsOutputDto
{
    /** @param list<PrintVersionOutputDto> $versions */
    public function __construct(
        public bool $allowed,
        public array $versions,
    ) {}
}
