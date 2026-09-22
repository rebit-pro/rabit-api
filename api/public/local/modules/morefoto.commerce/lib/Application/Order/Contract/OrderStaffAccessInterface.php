<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Contract;

use Morefoto\Commerce\Application\Order\Dto\OrderStaffScopeOutputDto;

interface OrderStaffAccessInterface
{
    /** Роль и область чтения заказов; head/teacher и неактивный сотрудник получают FORBIDDEN, недействительная сессия — UNAUTHORIZED. */
    public function scope(int $actorId): OrderStaffScopeOutputDto;

    /** Повторная сверка прав перед выдачей результата: изменение роли или назначений даёт 409 ACCESS_CHANGED. */
    public function assertUnchanged(int $actorId, OrderStaffScopeOutputDto $scope): void;
}
