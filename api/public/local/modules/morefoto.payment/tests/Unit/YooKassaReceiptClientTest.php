<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderReceiptInputDto;
use Morefoto\Payment\Domain\Receipt\Enum\PaymentModeEnum;
use Morefoto\Payment\Domain\Receipt\Enum\PaymentSubjectEnum;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptItem;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptSnapshot;
use Morefoto\Payment\Infrastructure\Config\PaymentConnectionConfig;
use Morefoto\Payment\Infrastructure\Http\PaymentClient;
use Morefoto\Payment\Infrastructure\Http\PaymentClientFactory;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaRequestMapper;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaResponseMapper;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Provider\YooKassaClientProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\HttpClient\RebitHttpClient;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * G2-T04, G2-T08: чек в запросе платежа, закрывающий чек и чтение чеков ЮKassa по OpenAPI-спецификации.
 *
 * @internal
 */
final class YooKassaReceiptClientTest extends TestCase
{
    public function testPaymentRequestCarriesTheReceiptWithoutTaxSystem(): void
    {
        $body = new YooKassaRequestMapper()->createPayment(new CreateProviderPaymentInputDto(105000, 'sbp', 'Заказ MF-0007', 'https://app.example.test/r', 'a-1', 'o-1', 'k-1', $this->receipt(PaymentModeEnum::FULL_PREPAYMENT)));

        self::assertSame([
            'customer' => ['email' => 'parent@example.test'],
            'items' => [[
                'description' => 'Печать 10×15',
                'quantity' => 2,
                'amount' => ['value' => '300.00', 'currency' => 'RUB'],
                'vat_code' => 1,
                'payment_mode' => 'full_prepayment',
                'payment_subject' => 'commodity',
                'measure' => 'piece',
            ]],
        ], $body['receipt'] ?? null);
        self::assertArrayNotHasKey('tax_system_code', $body['receipt']);
    }

    public function testClosingReceiptSettlesThePrepaymentOfThePayment(): void
    {
        $body = new YooKassaRequestMapper()->closingReceipt(new CreateProviderReceiptInputDto('yk-1', $this->receipt(PaymentModeEnum::FULL_PAYMENT), 60000, 'k-2'));

        self::assertSame(['payment', 'yk-1', true], [$body['type'], $body['payment_id'], $body['send']]);
        self::assertSame([['type' => 'prepayment', 'amount' => ['value' => '600.00', 'currency' => 'RUB']]], $body['settlements']);
        self::assertSame('full_payment', $body['items'][0]['payment_mode']);
        self::assertSame(['email' => 'parent@example.test'], $body['customer']);
    }

    public function testReceiptsAreReadWithFiscalRequisitesOnly(): void
    {
        $mapper = new YooKassaResponseMapper();
        $receipts = $mapper->receipts(['type' => 'list', 'items' => [
            ['id' => 'rt-2da5c87d-0384-50e8-a7f3-8d5646dd9e10', 'type' => 'payment', 'status' => 'succeeded', 'fiscal_document_number' => '3986',
                'fiscal_storage_number' => '9288000100115785', 'fiscal_attribute' => 2617603921, 'registered_at' => '2026-10-17T12:00:05.000+03:00'],
            ['id' => 'broken'],
            ['id' => 'rt-1', 'type' => 'refund', 'status' => 'pending', 'fiscal_document_number' => '<script>'],
        ]]);

        self::assertCount(2, $receipts);
        self::assertSame(['payment', 'succeeded', '3986', '9288000100115785', '2617603921', '2026-10-17 09:00:05'], [
            $receipts[0]->type, $receipts[0]->status, $receipts[0]->fiscalDocumentNumber, $receipts[0]->fiscalStorageNumber, $receipts[0]->fiscalAttribute, $receipts[0]->registeredAt,
        ]);
        self::assertNull($receipts[1]->fiscalDocumentNumber);
    }

    public function testReceiptCallsUseTheReceiptsEndpointAndTheStoredKey(): void
    {
        $http = $this->createMock(RebitHttpClient::class);
        $http->expects(self::once())->method('get')
            ->with('https://api.yookassa.ru/v3/receipts?payment_id=yk-1', [])
            ->willReturn(['type' => 'list', 'items' => []])
        ;
        $http->expects(self::once())->method('post')
            ->with('https://api.yookassa.ru/v3/receipts', self::callback(static fn(array $body): bool => 'yk-1' === $body['payment_id']), ['Content-Type' => 'application/json', 'Idempotence-Key' => 'k-2'])
            ->willReturn(['id' => 'rt-2', 'type' => 'payment', 'status' => 'pending'])
        ;
        $factory = $this->createStub(PaymentClientFactory::class);
        $factory->method('create')->willReturn(new PaymentClient($http, new PaymentConnectionConfig('https://api.yookassa.ru/v3/', '123456', 'test_secret')));
        $provider = new YooKassaClientProvider($factory, new YooKassaRequestMapper(), new YooKassaResponseMapper(), '123456');

        self::assertSame([], $provider->paymentReceipts('yk-1'));
        self::assertSame('rt-2', $provider->createReceipt(new CreateProviderReceiptInputDto('yk-1', $this->receipt(PaymentModeEnum::FULL_PAYMENT), 60000, 'k-2'))->id);
    }

    private function receipt(PaymentModeEnum $mode): ReceiptSnapshot
    {
        return new ReceiptSnapshot('parent@example.test', [new ReceiptItem('Печать 10×15', 2, 30000, true, $mode, PaymentSubjectEnum::COMMODITY, 1)]);
    }
}
