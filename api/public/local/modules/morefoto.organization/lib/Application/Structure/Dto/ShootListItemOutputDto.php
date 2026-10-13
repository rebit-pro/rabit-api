<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

final readonly class ShootListItemOutputDto
{
    /** @param null|string $date shoot day as Y-m-d; null while it is not planned */
    public function __construct(
        public string $id,
        public string $institutionId,
        public string $institutionName,
        public string $name,
        public ?string $date,
        public int $revision,
        public int $groupCount,
        public int $openGroupCount,
    ) {}
}
