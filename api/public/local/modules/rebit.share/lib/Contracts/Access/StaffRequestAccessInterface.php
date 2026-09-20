<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access;

use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;

interface StaffRequestAccessInterface
{
    /** Возвращает только активного сотрудника и серверную область из Access. */
    public function actor(int $userId): StaffRequestActorOutputDto;
}
