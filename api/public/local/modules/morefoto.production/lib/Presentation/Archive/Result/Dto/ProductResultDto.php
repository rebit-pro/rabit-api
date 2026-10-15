<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class ProductResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $name,
        public string $format,
        public int $files,
        public int $prints,
    ) {}
}
