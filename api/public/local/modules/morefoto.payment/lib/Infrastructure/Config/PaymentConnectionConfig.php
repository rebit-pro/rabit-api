<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Config;

/** Реквизиты подключения к платёжному шлюзу; секрет приходит только из окружения или Docker secret. */
final readonly class PaymentConnectionConfig
{
    public function __construct(
        public string $gatewayUrl,
        public string $shopId,
        public string $secretKey,
    ) {}
}
