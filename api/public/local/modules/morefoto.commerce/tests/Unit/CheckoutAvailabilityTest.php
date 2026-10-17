<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Order\Service\CheckoutAvailability;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Payment\PaymentCapabilitiesInterface;

require_once __DIR__ . '/../bootstrap.php';

/**
 * G2-T10, G2-T11: каналы чека и тестовый режим оплаты приходят в оформление из Payment.
 *
 * @internal
 */
final class CheckoutAvailabilityTest extends TestCase
{
    public function testWithoutPaymentThereAreNoReceipts(): void
    {
        $checkout = new CheckoutAvailability(true);

        self::assertSame([[], false], [$checkout->receiptChannels(), $checkout->paymentTestMode()]);
    }

    public function testPaymentDeclaresTheEmailReceiptAndTheTestShop(): void
    {
        $payment = new readonly class implements PaymentCapabilitiesInterface {
            public function receiptChannels(): array
            {
                return ['email'];
            }

            public function testMode(): bool
            {
                return true;
            }
        };
        $checkout = new CheckoutAvailability(true, false, $payment);

        self::assertSame([['email'], true], [$checkout->receiptChannels(), $checkout->paymentTestMode()]);
    }
}
