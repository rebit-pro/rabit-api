<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class WorksheetFileOutputDto
{
    public function __construct(
        public string $photoId,
        public ?string $filename,
        public ?string $path,
        public ?int $printsInFile,
        public ?string $code = null,
        public ?string $childCode = null,
    ) {}
}
