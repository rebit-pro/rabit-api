<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Application\Payment\Service\PaymentSettings;
use Morefoto\Payment\Application\Receipt\Service\ReceiptSettings;
use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;
use Morefoto\Payment\Domain\Receipt\Enum\PaymentSubjectEnum;
use Morefoto\Payment\Infrastructure\Commerce\PaymentCapabilities;
use Morefoto\Payment\Infrastructure\Config\ReceiptSettingsFactory;
use Morefoto\Payment\Tests\Unit\Support\FakeOrders;
use Morefoto\Payment\Tests\Unit\Support\PaymentScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * G2-T05, G2-T10, G2-T11: настройки чеков, тестовый режим магазина и каналы чека для оформления.
 *
 * @internal
 */
final class ReceiptSettingsTest extends TestCase
{
    public function testReceiptsStayOffUntilRequested(): void
    {
        foreach (['', 'off'] as $mode) {
            $settings = new ReceiptSettingsFactory(new NullLogger())->create($mode, '', '', '');
            self::assertSame([false, false], [$settings->enabled(), $settings->misconfigured()]);
        }
    }

    public function testOwnerDefaultsAreNoVatAndCommodity(): void
    {
        $settings = new ReceiptSettingsFactory(new NullLogger())->create('yookassa', '', '', ' service ');

        self::assertTrue($settings->enabled());
        self::assertSame([1, PaymentSubjectEnum::COMMODITY, PaymentSubjectEnum::SERVICE], [$settings->rules()->vatCode, $settings->rules()->printSubject, $settings->rules()->digitalSubject]);
    }

    #[DataProvider('invalid')]
    public function testInvalidValueDisablesPayment(string $mode, string $vat, string $print): void
    {
        $settings = new ReceiptSettingsFactory(new NullLogger())->create($mode, $vat, $print, '');
        $payment = new PaymentSettings(!$settings->misconfigured(), [PaymentMethodEnum::SBP], 'https://app.example.test');

        self::assertSame([false, true, false], [$settings->enabled(), $settings->misconfigured(), $payment->enabled()]);
    }

    public static function invalid(): iterable
    {
        yield 'unknown provider' => ['atol', '1', ''];
        yield 'VAT zero' => ['yookassa', '0', ''];
        yield 'VAT above the list' => ['yookassa', '13', ''];
        yield 'VAT text' => ['yookassa', 'без НДС', ''];
        yield 'unknown subject' => ['yookassa', '1', 'photo'];
    }

    public function testCheckoutLearnsReceiptChannelAndTestModeFromPayment(): void
    {
        $scenario = new PaymentScenario(receiptsEnabled: true);
        $on = new ReceiptSettings(true, $scenario->receiptSettings()->rules());
        $test = new PaymentSettings(true, [PaymentMethodEnum::BANK_CARD], 'https://app.example.test', true);
        $live = new PaymentSettings(true, [PaymentMethodEnum::SBP], 'https://app.example.test', false);
        $disabled = new PaymentSettings(false, [PaymentMethodEnum::SBP], 'https://app.example.test', true);

        self::assertSame([['email'], true], [new PaymentCapabilities($test, $on)->receiptChannels(), new PaymentCapabilities($test, $on)->testMode()]);
        self::assertSame([['email'], false], [new PaymentCapabilities($live, $on)->receiptChannels(), new PaymentCapabilities($live, $on)->testMode()]);
        self::assertSame([[], false], [new PaymentCapabilities($disabled, $on)->receiptChannels(), new PaymentCapabilities($disabled, $on)->testMode()]);
        self::assertSame([], new PaymentCapabilities($test, new ReceiptSettings(false, null))->receiptChannels());
    }

    public function testQuoteTellsTheBuyerAboutTheTestShop(): void
    {
        $scenario = new PaymentScenario();

        self::assertFalse($scenario->quote()->execute(FakeOrders::KEY)->testMode);
    }
}
