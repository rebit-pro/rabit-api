<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderRejectedException;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;
use Morefoto\Payment\Infrastructure\Config\PaymentConnectionConfig;
use Morefoto\Payment\Infrastructure\Http\Exception\PaymentHttpException;
use Morefoto\Payment\Infrastructure\Http\PaymentClient;
use Morefoto\Payment\Infrastructure\Http\PaymentClientFactory;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaRequestMapper;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaResponseMapper;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Provider\YooKassaClientProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\HttpClient\Exception\HttpClientException;
use Rebit\Share\Infrastructure\HttpClient\RebitHttpClient;
use Rebit\Share\Shared\Enum\HttpMethodEnum;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * G1-T17, G1-T18: запрос ЮKassa по выбранному способу, разбор ответа и классификация отказов.
 *
 * @internal
 */
final class YooKassaClientTest extends TestCase
{
    public function testCreatePaymentRequestCarriesMethodAmountAndAttempt(): void
    {
        $body = new YooKassaRequestMapper()->createPayment(new CreateProviderPaymentInputDto(
            amount: 105005,
            paymentMethod: 'sbp',
            description: 'Заказ MF-0007',
            returnUrl: 'https://app.example.test/orders/payment/a-1',
            attemptId: 'a-1',
            orderId: 'o-1',
            idempotenceKey: 'k-1',
        ));

        self::assertSame([
            'amount' => ['value' => '1050.05', 'currency' => 'RUB'],
            'capture' => true,
            'payment_method_data' => ['type' => 'sbp'],
            'confirmation' => ['type' => 'redirect', 'return_url' => 'https://app.example.test/orders/payment/a-1'],
            'description' => 'Заказ MF-0007',
            'metadata' => ['attemptId' => 'a-1', 'orderId' => 'o-1'],
        ], $body);
    }

    public function testPaymentObjectIsReadInMinorUnitsAndUtc(): void
    {
        $payment = new YooKassaResponseMapper()->payment([
            'id' => '2d7f1f6c-000f-5000-9000-1a1b2c3d4e5f',
            'status' => 'succeeded',
            'amount' => ['value' => '1050.5', 'currency' => 'RUB'],
            'income_amount' => ['value' => '1010.60', 'currency' => 'RUB'],
            'recipient' => ['account_id' => '123456', 'gateway_id' => '1'],
            'captured_at' => '2026-09-25T09:03:04.123Z',
            'metadata' => ['attemptId' => 'a-1'],
            'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/checkout/x'],
            'test' => true,
        ]);

        self::assertSame(105050, $payment->amount);
        self::assertSame(101060, $payment->incomeAmount);
        self::assertSame('123456', $payment->shopId);
        self::assertSame('a-1', $payment->attemptId);
        self::assertSame('2026-09-25 09:03:04', $payment->paidAt);
        self::assertSame('https://yoomoney.ru/checkout/x', $payment->confirmationUrl);
    }

    public function testMalformedAmountNeverMatchesAnAttempt(): void
    {
        foreach (['1e3', '-5.00', '10.123', ''] as $value) {
            self::assertSame(-1, new YooKassaResponseMapper()->payment(['id' => 'p', 'status' => 'pending', 'amount' => ['value' => $value]])->amount);
        }
    }

    public function testAnswerWithoutPaymentObjectIsAnUnknownOutcome(): void
    {
        $this->expectException(ProviderUnavailableException::class);
        new YooKassaResponseMapper()->payment(['type' => 'processing', 'retry_after' => 1800]);
    }

    public function testClientSendsBasicAuthIdempotenceKeyAndJsonToTheGateway(): void
    {
        $http = $this->createMock(RebitHttpClient::class);
        $http->expects(self::once())->method('setAuthorization')->with('123456', 'test_secret');
        $http->expects(self::once())->method('post')
            ->with('https://api.yookassa.ru/v3/payments', ['a' => 1], ['Content-Type' => 'application/json', 'Idempotence-Key' => 'k-1'])
            ->willReturn(['id' => 'p'])
        ;
        $client = new PaymentClient($http, new PaymentConnectionConfig('https://api.yookassa.ru/v3/', '123456', 'test_secret'));

        self::assertSame(['id' => 'p'], $client->request('payments', HttpMethodEnum::POST, ['a' => 1], ['Content-Type' => 'application/json', 'Idempotence-Key' => 'k-1']));
    }

    #[DataProvider('failures')]
    public function testGatewayFailureIsClassified(\Throwable $failure, string $expected): void
    {
        $http = $this->createStub(RebitHttpClient::class);
        $http->method('get')->willThrowException($failure);
        $client = new PaymentClient($http, new PaymentConnectionConfig('https://api.yookassa.ru/v3/', '123456', 'test_secret'));
        $factory = $this->createStub(PaymentClientFactory::class);
        $factory->method('create')->willReturn($client);
        $provider = new YooKassaClientProvider($factory, new YooKassaRequestMapper(), new YooKassaResponseMapper(), '123456');

        $this->expectException($expected);
        $provider->find('2d7f1f6c-000f-5000-9000-1a1b2c3d4e5f');
    }

    public static function failures(): iterable
    {
        yield 'bad request' => [new HttpClientException('HTTP error: status 400', 400), ProviderRejectedException::class];
        yield 'wrong credentials' => [new HttpClientException('HTTP error: status 401', 401), ProviderRejectedException::class];
        yield 'unknown payment' => [new HttpClientException('HTTP error: status 404', 404), ProviderRejectedException::class];
        yield 'rate limited' => [new HttpClientException('HTTP error: status 429', 429), ProviderUnavailableException::class];
        yield 'server error' => [new HttpClientException('HTTP error: status 500', 500), ProviderUnavailableException::class];
        yield 'timeout' => [new HttpClientException('HTTP request failed: timeout'), ProviderUnavailableException::class];
        yield 'broken JSON' => [new \JsonException('Syntax error'), ProviderUnavailableException::class];
    }

    public function testMalformedProviderIdIsNeverSentToTheGateway(): void
    {
        $factory = $this->createMock(PaymentClientFactory::class);
        $factory->expects(self::never())->method('create');

        $this->expectException(ProviderRejectedException::class);
        new YooKassaClientProvider($factory, new YooKassaRequestMapper(), new YooKassaResponseMapper(), '123456')->find('../refunds');
    }

    public function testHttpExceptionKeepsTheGatewayStatus(): void
    {
        $http = $this->createStub(RebitHttpClient::class);
        $http->method('get')->willThrowException(new HttpClientException('HTTP error: status 503', 503));

        try {
            new PaymentClient($http, new PaymentConnectionConfig('https://api.yookassa.ru/v3/', '123456', 'test_secret'))->request('payments/x', HttpMethodEnum::GET);
            self::fail('Expected a gateway failure.');
        } catch (PaymentHttpException $error) {
            self::assertSame(503, $error->getCode());
            self::assertStringNotContainsString('test_secret', $error->getMessage());
        }
    }
}
