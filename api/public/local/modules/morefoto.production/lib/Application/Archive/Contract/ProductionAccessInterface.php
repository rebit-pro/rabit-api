<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Contract;

use Morefoto\Production\Application\Archive\Dto\ProductionScopeOutputDto;

interface ProductionAccessInterface
{
    /** Организатор видит все группы и управляет архивом, куратор — только смотрит свои учреждения; остальным FORBIDDEN. */
    public function scope(int $actorId): ProductionScopeOutputDto;
}
