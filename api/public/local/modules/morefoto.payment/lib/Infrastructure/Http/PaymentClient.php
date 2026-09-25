<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Http;

use Morefoto\Payment\Infrastructure\Config\PaymentConnectionConfig;
use Morefoto\Payment\Infrastructure\Http\Exception\PaymentHttpException;
use Rebit\Share\Infrastructure\HttpClient\RebitHttpClient;
use Rebit\Share\Shared\Enum\HttpMethodEnum;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class PaymentClient
{
    public function __construct(
        private RebitHttpClient $httpClient,
        private PaymentConnectionConfig $config,
    ) {}

    public function getConfig(): PaymentConnectionConfig
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed>  $parameters
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     *
     * @throws PaymentHttpException
     */
    public function request(
        string $endpoint,
        HttpMethodEnum $method,
        array $parameters = [],
        array $headers = [],
    ): array {
        $url = rtrim($this->config->gatewayUrl, '/') . '/' . ltrim($endpoint, '/');
        $this->httpClient->setAuthorization($this->config->shopId, $this->config->secretKey);

        try {
            return HttpMethodEnum::GET === $method
                ? $this->httpClient->get([] === $parameters ? $url : $url . '?' . http_build_query($parameters), $headers)
                : $this->httpClient->post($url, $parameters, $headers);
        } catch (\Throwable $e) {
            // The gateway status is kept as the code: callers tell a final refusal from an unknown outcome by it.
            throw new PaymentHttpException(
                message: sprintf('Payment request failed: %s', $e->getMessage()),
                code: $e instanceof HttpException && 400 <= $e->getCode() ? $e->getCode() : 0,
                previous: $e instanceof \Exception ? $e : null,
            );
        }
    }
}
