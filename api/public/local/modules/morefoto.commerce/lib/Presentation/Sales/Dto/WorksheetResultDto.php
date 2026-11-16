<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class WorksheetResultDto implements ResultDtoInterface
{
    /**
     * @param list<WorksheetVersionResultDto> $versions
     * @param list<WorksheetGroupResultDto>   $groups
     * @param list<WorksheetColumnResultDto>  $columns
     */
    public function __construct(
        public string $mode,
        public ?WorksheetVersionResultDto $archive,
        public bool $finalAllowed,
        public array $versions,
        public array $groups,
        public array $columns,
        public int $total,
        public int $electronicTotal,
        public int $prints,
        public string $generatedAt,
    ) {}
}
