<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Provider\YooKassa\Provider;

use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderRejectedException;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;
use Morefoto\Payment\Infrastructure\Http\Exception\PaymentHttpException;
use Morefoto\Payment\Infrastructure\Http\PaymentClientFactory;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaRequestMapper;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaResponseMapper;
use Rebit\Share\Shared\Enum\HttpMethodEnum;

/**
 * Обращения к API ЮKassa v3: создание платежа и запрос его состояния.
 *
 * Авторизация — Basic shopId:secretKey; повтор создания идёт с сохранённым Idempotence-Key попытки.
 */
final readonly class YooKassaClientProvider implements PaymentProviderInterface
{
    private const string CODE = 'yookassa';
    private const string PAYMENTS_ENDPOINT = 'payments';
    /** Окончательный отказ шлюза; 429, 5xx и отсутствие ответа означают неизвестный исход. */
    private const array REJECTED_STATUSES = [400, 401, 403, 404];

    public function __construct(
        private PaymentClientFactory $clientFactory,
        private YooKassaRequestMapper $requestMapper,
        private YooKassaResponseMapper $responseMapper,
        private string $shopId,
    ) {}

    public function code(): string
    {
        return self::CODE;
    }

    public function shopId(): string
    {
        return $this->shopId;
    }

    public function create(CreateProviderPaymentInputDto $input): ProviderPaymentOutputDto
    {
        return $this->request(HttpMethodEnum::POST, self::PAYMENTS_ENDPOINT, $this->requestMapper->createPayment($input), [
            'Content-Type' => 'application/json',
            'Idempotence-Key' => $input->idempotenceKey,
        ]);
    }

    public function find(string $providerPaymentId): ProviderPaymentOutputDto
    {
        if (1 !== preg_match('/^[A-Za-z0-9-]{1,64}$/D', $providerPaymentId)) {
            throw new ProviderRejectedException('Malformed YooKassa payment ID.');
        }

        return $this->request(HttpMethodEnum::GET, self::PAYMENTS_ENDPOINT . '/' . $providerPaymentId);
    }

    /**
     * @param array<string, mixed>  $parameters
     * @param array<string, string> $headers
     */
    private function request(HttpMethodEnum $method, string $endpoint, array $parameters = [], array $headers = []): ProviderPaymentOutputDto
    {
        try {
            $response = $this->clientFactory->create()->request($endpoint, $method, $parameters, $headers);
        } catch (PaymentHttpException $error) {
            throw in_array($error->getCode(), self::REJECTED_STATUSES, true)
                ? new ProviderRejectedException('YooKassa rejected the request: HTTP ' . $error->getCode(), 0, $error)
                : new ProviderUnavailableException('YooKassa outcome is unknown: HTTP ' . $error->getCode(), 0, $error);
        }

        return $this->responseMapper->payment($response);
    }
}
