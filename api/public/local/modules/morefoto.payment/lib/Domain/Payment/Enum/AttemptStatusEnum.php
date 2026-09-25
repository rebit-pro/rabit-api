<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\Enum;

enum AttemptStatusEnum: string
{
    /** Исход у провайдера не известен: платёж не создан или ответ не получен. */
    case UNKNOWN = 'unknown';
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case CANCELED = 'canceled';

    /** Открытая попытка блокирует новую оплату заказа до конечного исхода. */
    public function isOpen(): bool
    {
        return self::UNKNOWN === $this || self::PENDING === $this;
    }
}
