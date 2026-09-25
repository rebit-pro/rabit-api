<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Http;

use Morefoto\Payment\Infrastructure\Config\PaymentConnectionConfig;
use Psr\Log\LoggerInterface;
use Rebit\Share\Infrastructure\HttpClient\RebitHttpClientFactory;

final readonly class PaymentClientFactory
{
    /** Покупатель ждёт создания платежа в HTTP-запросе: короткие таймауты, превышение даёт неизвестный исход. */
    private const int SOCKET_TIMEOUT = 5;
    private const int STREAM_TIMEOUT = 15;

    public function __construct(
        private LoggerInterface $logger,
        private PaymentConnectionConfig $config,
    ) {}

    public function create(): PaymentClient
    {
        // RebitHttpClient keeps its authorization state, so every PaymentClient owns its instance.
        return new PaymentClient(RebitHttpClientFactory::create($this->logger, self::SOCKET_TIMEOUT, self::STREAM_TIMEOUT), $this->config);
    }
}
