<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Dto;

final readonly class ProductionRunOutputDto
{
    /** @param int $failed отклонённые снимки групп и неудавшиеся сборки архивов за прогон */
    public function __construct(
        public int $scheduled,
        public int $built,
        public int $failed,
        public int $expired,
    ) {}
}
