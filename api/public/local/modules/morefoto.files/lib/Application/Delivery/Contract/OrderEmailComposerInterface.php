<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Contract;

use Morefoto\Files\Application\Delivery\Dto\OrderEmailTextOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;

interface OrderEmailComposerInterface
{
    /** Тема, текст и HTML письма со ссылкой на страницу оплаченного заказа; одинаковые данные дают одинаковое письмо. */
    public function compose(OrderEmailOutputDto $order, string $orderKey, \DateTimeImmutable $expiresAt): OrderEmailTextOutputDto;
}
