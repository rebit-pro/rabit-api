<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class WorksheetFileResultDto implements ResultDtoInterface
{
    public function __construct(
        public ?string $filename,
        public ?string $path,
        public ?int $printsInFile,
        public ?string $code = null,
        public ?string $childCode = null,
    ) {}
}
