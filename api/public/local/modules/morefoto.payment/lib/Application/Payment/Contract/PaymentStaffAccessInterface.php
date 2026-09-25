<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Contract;

use Morefoto\Payment\Application\Payment\Dto\PaymentStaffScopeOutputDto;

interface PaymentStaffAccessInterface
{
    /** Область чтения платежей как у заказов: organizer — все, curator — свои учреждения, остальные — FORBIDDEN. */
    public function scope(int $actorId): PaymentStaffScopeOutputDto;

    /** Изменение роли или назначений во время чтения даёт 409 ACCESS_CHANGED. */
    public function assertUnchanged(int $actorId, PaymentStaffScopeOutputDto $scope): void;
}
