<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class PayableOrderOutputDto
{
    /**
     * @param int         $id            внутренний ID заказа
     * @param int         $institutionId внутренний ID учреждения для области доступа сотрудника
     * @param int         $total         итог снимка заказа в копейках
     * @param string      $version       версия заказа для оптимистичной проверки оплаты
     * @param null|string $paidAt        момент оплаты UTC `Y-m-d H:i:s`
     * @param null|string $salesUntil    срок продаж состава заказа UTC `Y-m-d H:i:s`: приём печати, если в заказе есть
     *                                   печать, иначе продажа электронных фото; null — срок ещё не назначен
     * @param bool        $containsPrint в снимке заказа есть печатная продукция
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $number,
        public int $institutionId,
        public string $institutionName,
        public string $groupName,
        public int $subtotal,
        public int $discount,
        public int $giftSaving,
        public int $total,
        public string $paymentStatus,
        public string $version,
        public ?string $paidAt,
        public bool $latePayment,
        public ?string $salesUntil,
        public bool $containsPrint,
    ) {}
}
