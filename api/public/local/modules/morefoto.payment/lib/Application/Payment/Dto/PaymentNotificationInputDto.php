<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class PaymentNotificationInputDto
{
    public function __construct(
        public string $provider,
        public string $event,
        public string $objectId,
    ) {}
}
