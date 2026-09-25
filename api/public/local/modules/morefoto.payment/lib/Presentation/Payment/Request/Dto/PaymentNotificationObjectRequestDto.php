<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Request\Dto;

/** Объект платежа в уведомлении: сервер берёт только ID, остальные поля провайдера отбрасываются. */
final readonly class PaymentNotificationObjectRequestDto
{
    public function __construct(
        public string $id = '',
    ) {}
}
