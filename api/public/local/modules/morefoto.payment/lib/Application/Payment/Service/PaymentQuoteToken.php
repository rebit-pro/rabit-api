<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Service;

use Rebit\Share\Contracts\Commerce\Dto\PayableOrderOutputDto;

/** Связывает подтверждение покупателя с тем итогом и версией заказа, которые он видел перед оплатой:
 * изменение версии или суммы делает старый токен недействительным. Это не секрет — доступ даёт личный ключ.
 */
final readonly class PaymentQuoteToken
{
    public function token(PayableOrderOutputDto $order): string
    {
        return hash('sha256', $order->publicId . '|' . $order->version . '|' . $order->total);
    }
}
