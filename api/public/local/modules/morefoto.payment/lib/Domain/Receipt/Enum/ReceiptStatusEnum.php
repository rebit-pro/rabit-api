<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\Enum;

/** queued — закрывающий чек ждёт регистрации чека оплаты; pending — у провайдера; canceled — провайдер не зарегистрировал;
 * failed — запрос отклонён, чек оплаты не зарегистрирован или регистрация не завершилась в срок.
 */
enum ReceiptStatusEnum: string
{
    case QUEUED = 'queued';
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case CANCELED = 'canceled';
    case FAILED = 'failed';

    public function isOpen(): bool
    {
        return self::QUEUED === $this || self::PENDING === $this;
    }

    /** Состояние для покупателя и сотрудника: sent — чек зарегистрирован и отправлен провайдером на email заказа. */
    public function visible(): string
    {
        return match ($this) {
            self::QUEUED, self::PENDING => 'pending',
            self::SUCCEEDED => 'sent',
            self::CANCELED, self::FAILED => 'failed',
        };
    }
}
