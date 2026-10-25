<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Contract;

use Morefoto\Commerce\Application\Sales\Dto\SalesScopeOutputDto;

interface SalesAccessInterface
{
    /** Область продаж E9/E10: организатор — всё, куратор и руководитель — свои учреждения, воспитатель — свои группы; остальным FORBIDDEN. */
    public function scope(int $actorId): SalesScopeOutputDto;
}
