<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Contract;

interface CheckoutKeySealInterface
{
    /** Шифрует личный ключ заказа ключом, выведенным из Idempotency-Key клиента; результат пригоден для хранения. */
    public function seal(string $accessKey, string $idempotencyKey, string $context): string;

    /** Восстанавливает личный ключ для повтора того же оформления; неверный ключ повтора даёт исключение. */
    public function open(string $sealed, string $idempotencyKey, string $context): string;
}
