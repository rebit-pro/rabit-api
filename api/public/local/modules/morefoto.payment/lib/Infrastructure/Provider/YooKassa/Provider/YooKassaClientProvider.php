<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Provider\YooKassa\Provider;

use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderReceiptInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderReceiptOutputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderRejectedException;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;
use Morefoto\Payment\Infrastructure\Http\Exception\PaymentHttpException;
use Morefoto\Payment\Infrastructure\Http\PaymentClientFactory;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaRequestMapper;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaResponseMapper;
use Rebit\Share\Shared\Enum\HttpMethodEnum;

/**
 * Обращения к API ЮKassa v3: создание платежа и запрос его состояния, закрывающий чек и чтение чеков платежа.
 *
 * Авторизация — Basic shopId:secretKey; повтор создания идёт с сохранённым Idempotence-Key попытки.
 */
final readonly class YooKassaClientProvider implements PaymentProviderInterface
{
    private const string CODE = 'yookassa';
    private const string PAYMENTS_ENDPOINT = 'payments';
    private const string RECEIPTS_ENDPOINT = 'receipts';
    private const string ID_PATTERN = '/^[A-Za-z0-9-]{1,64}$/D';
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
        return $this->responseMapper->payment($this->request(HttpMethodEnum::POST, self::PAYMENTS_ENDPOINT, $this->requestMapper->createPayment($input), [
            'Content-Type' => 'application/json',
            'Idempotence-Key' => $input->idempotenceKey,
        ]));
    }

    public function find(string $providerPaymentId): ProviderPaymentOutputDto
    {
        if (1 !== preg_match(self::ID_PATTERN, $providerPaymentId)) {
            throw new ProviderRejectedException('Malformed YooKassa payment ID.');
        }

        return $this->responseMapper->payment($this->request(HttpMethodEnum::GET, self::PAYMENTS_ENDPOINT . '/' . $providerPaymentId));
    }

    public function createReceipt(CreateProviderReceiptInputDto $input): ProviderReceiptOutputDto
    {
        return $this->responseMapper->receipt($this->request(HttpMethodEnum::POST, self::RECEIPTS_ENDPOINT, $this->requestMapper->closingReceipt($input), [
            'Content-Type' => 'application/json',
            'Idempotence-Key' => $input->idempotenceKey,
        ]));
    }

    public function findReceipt(string $providerReceiptId): ProviderReceiptOutputDto
    {
        if (1 !== preg_match(self::ID_PATTERN, $providerReceiptId)) {
            throw new ProviderRejectedException('Malformed YooKassa receipt ID.');
        }

        return $this->responseMapper->receipt($this->request(HttpMethodEnum::GET, self::RECEIPTS_ENDPOINT . '/' . $providerReceiptId));
    }

    public function paymentReceipts(string $providerPaymentId): array
    {
        if (1 !== preg_match(self::ID_PATTERN, $providerPaymentId)) {
            throw new ProviderRejectedException('Malformed YooKassa payment ID.');
        }

        return $this->responseMapper->receipts($this->request(HttpMethodEnum::GET, self::RECEIPTS_ENDPOINT, ['payment_id' => $providerPaymentId]));
    }

    /**
     * @param array<string, mixed>  $parameters
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    private function request(HttpMethodEnum $method, string $endpoint, array $parameters = [], array $headers = []): array
    {
        try {
            $response = $this->clientFactory->create()->request($endpoint, $method, $parameters, $headers);
        } catch (PaymentHttpException $error) {
            throw in_array($error->getCode(), self::REJECTED_STATUSES, true)
                ? new ProviderRejectedException('YooKassa rejected the request: HTTP ' . $error->getCode(), 0, $error)
                : new ProviderUnavailableException('YooKassa outcome is unknown: HTTP ' . $error->getCode(), 0, $error);
        }

        return $response;
    }
}
