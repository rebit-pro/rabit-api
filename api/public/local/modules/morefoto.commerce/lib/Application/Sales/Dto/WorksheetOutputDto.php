<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

use Rebit\Share\Contracts\Production\Dto\PrintVersionOutputDto;

final readonly class WorksheetOutputDto
{
    /**
     * @param list<PrintVersionOutputDto>    $versions
     * @param list<WorksheetGroupOutputDto>  $groups
     * @param list<WorksheetColumnOutputDto> $columns
     */
    public function __construct(
        public string $mode,
        public ?PrintVersionOutputDto $archive,
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
