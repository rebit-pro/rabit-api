<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Dto;

final readonly class OrderEmailSummaryOutputDto
{
    /**
     * @param bool                    $enabled           J2-DEC-05: отправка включена на сервере
     * @param null|string             $recipient         email заказа; null — заказ не оплачен
     * @param bool                    $canResend         оплаченный заказ в сроке файлов, отправка включена
     * @param null|\DateTimeImmutable $resendAvailableAt раньше этого момента повтор отвечает DELIVERY_TOO_SOON
     */
    public function __construct(
        public bool $enabled,
        public ?string $recipient,
        public ?DeliveryOutputDto $latest,
        public bool $canResend,
        public ?\DateTimeImmutable $resendAvailableAt,
    ) {}
}
