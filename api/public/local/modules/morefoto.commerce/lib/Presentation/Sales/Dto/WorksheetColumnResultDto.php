<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class WorksheetColumnResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $key,
        public string $name,
        public string $format,
    ) {}
}
