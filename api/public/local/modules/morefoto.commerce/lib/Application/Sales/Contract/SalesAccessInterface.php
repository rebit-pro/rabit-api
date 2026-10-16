<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Contract;

use Morefoto\Commerce\Application\Sales\Dto\SalesScopeOutputDto;

interface SalesAccessInterface
{
    /** Область сводки продаж E9: организатор — всё, куратор — свои учреждения, воспитатель — свои группы; остальным FORBIDDEN. */
    public function scope(int $actorId): SalesScopeOutputDto;
}
