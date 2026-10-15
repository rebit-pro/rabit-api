<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class OrderEmailOutputDto
{
    /**
     * @param int    $id         внутренний ID заказа
     * @param string $email      email покупателя из заказа (E8)
     * @param string $paidAt     подтверждённая оплата, UTC `Y-m-d H:i:s`
     * @param string $recordedAt момент, когда сервер подтвердил оплату, UTC `Y-m-d H:i:s`
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $number,
        public string $email,
        public string $paidAt,
        public string $recordedAt,
    ) {}
}
