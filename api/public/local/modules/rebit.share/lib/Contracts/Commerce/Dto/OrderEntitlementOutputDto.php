<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class OrderEntitlementOutputDto
{
    /**
     * @param int                               $id                  внутренний ID заказа
     * @param int                               $shootId             внутренний ID съёмки Media
     * @param null|string                       $paidAt              первый подтверждённый платёж UTC `Y-m-d H:i:s`
     * @param null|string                       $filesAvailableUntil конец срока файлов по D10 UTC `Y-m-d H:i:s`; null — заказ не оплачен
     * @param list<OrderEntitledPhotoOutputDto> $photos              кадры строк digital в порядке заказа
     * @param list<int>                         $childIds            внутренние ID детей Media с комплектом: строка bundle или подарок
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $number,
        public int $shootId,
        public string $paymentStatus,
        public ?string $paidAt,
        public bool $latePayment,
        public ?string $filesAvailableUntil,
        public array $photos,
        public array $childIds,
    ) {}
}
