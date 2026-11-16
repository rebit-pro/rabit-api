<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Production\Dto;

final readonly class PrintCompositionOutputDto
{
    /** @param list<PrintLineOutputDto> $lines все исходные позиции, включая общие файлы */
    public function __construct(
        public PrintVersionOutputDto $version,
        public array $lines,
    ) {}
}
