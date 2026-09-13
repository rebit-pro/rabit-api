<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

use Morefoto\Organization\Application\Structure\Dto\StructurePageInputDto;

final readonly class InstitutionDetailInputDto
{
    public StructurePageInputDto $shoots;
    public StructurePageInputDto $groups;

    public function __construct(int $shootsPage = 1, int $groupsPage = 1, int $pageSize = 50)
    {
        $this->shoots = new StructurePageInputDto($shootsPage, $pageSize);
        $this->groups = new StructurePageInputDto($groupsPage, $pageSize);
    }
}
