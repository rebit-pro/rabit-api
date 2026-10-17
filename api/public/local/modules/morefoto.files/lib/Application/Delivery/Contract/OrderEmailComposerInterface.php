<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Contract;

use Morefoto\Files\Application\Delivery\Dto\OrderEmailTextOutputDto;
use Morefoto\Files\Application\Delivery\Dto\OrderLetterInputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;

interface OrderEmailComposerInterface
{
    /**
     * Тема, текст и HTML одного письма отправки: какие кадры во вложении, какое это письмо по счёту и ссылка на страницу
     * оплаченного заказа. Одинаковые данные дают одинаковое письмо.
     */
    public function compose(OrderEmailOutputDto $order, string $orderKey, \DateTimeImmutable $expiresAt, OrderLetterInputDto $letter): OrderEmailTextOutputDto;
}
