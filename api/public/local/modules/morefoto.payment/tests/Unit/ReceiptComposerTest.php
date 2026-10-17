<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Domain\Receipt\Enum\PaymentModeEnum;
use Morefoto\Payment\Domain\Receipt\Enum\PaymentSubjectEnum;
use Morefoto\Payment\Domain\Receipt\Service\ReceiptComposer;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptItem;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptLine;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptRules;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * G2-T02, G2-T03: состав чека оплаты и закрывающего чека по схеме владельца (G2-D09, G2-DEC-01/02).
 *
 * @internal
 */
final class ReceiptComposerTest extends TestCase
{
    public function testMixedOrderGroupsLinesSkipsGiftsAndSeparatesPrintFromDigital(): void
    {
        $rules = new ReceiptRules(1, PaymentSubjectEnum::COMMODITY, PaymentSubjectEnum::SERVICE);
        $lines = [
            new ReceiptLine(true, 'Печать 10×15', 1, 30000, 30000),
            new ReceiptLine(true, 'Печать 10×15', 2, 30000, 60000),
            new ReceiptLine(true, 'Печать 10×15', 1, 15000, 15000),
            new ReceiptLine(false, 'Электронная фотография', 1, 45000, 45000),
            new ReceiptLine(false, 'Электронная фотография', 1, 45000, 45000),
            new ReceiptLine(false, 'Комплект', 1, 90000, 90000),
            new ReceiptLine(false, 'Комплект', 1, 90000, 0),
        ];

        $receipt = new ReceiptComposer()->payment('parent@example.test', $lines, 285000, $rules);

        self::assertSame('parent@example.test', $receipt->email);
        self::assertSame([
            ['Печать 10×15', 3, 30000, true, 'full_prepayment', 'commodity', 1],
            ['Печать 10×15', 1, 15000, true, 'full_prepayment', 'commodity', 1],
            ['Электронная фотография', 2, 45000, false, 'full_payment', 'service', 1],
            ['Комплект', 1, 90000, false, 'full_payment', 'service', 1],
        ], array_map(static fn(ReceiptItem $item): array => [$item->description, $item->quantity, $item->amount, $item->print, $item->mode->value, $item->subject->value, $item->vatCode], $receipt->items));
        self::assertSame(285000, $receipt->total(), 'A gift bundle is paid by nobody and stays out of the receipt.');
    }

    public function testClosingReceiptSettlesOnlyThePrintAsFullPayment(): void
    {
        $rules = new ReceiptRules(1, PaymentSubjectEnum::COMMODITY, PaymentSubjectEnum::COMMODITY);
        $payment = new ReceiptComposer()->payment('p@example.test', [new ReceiptLine(true, 'Печать 15×21', 2, 40000, 80000), new ReceiptLine(false, 'Электронная фотография', 1, 45000, 45000)], 125000, $rules);

        $closing = new ReceiptComposer()->closing($payment->items);

        self::assertCount(1, $closing);
        self::assertSame(['Печать 15×21', 2, 40000, PaymentModeEnum::FULL_PAYMENT], [$closing[0]->description, $closing[0]->quantity, $closing[0]->amount, $closing[0]->mode]);
        self::assertSame([], new ReceiptComposer()->closing([$payment->items[1]]), 'A digital-only order has nothing to close.');
    }

    public function testNamesFitTheProviderLimits(): void
    {
        $rules = new ReceiptRules(1, PaymentSubjectEnum::COMMODITY, PaymentSubjectEnum::COMMODITY);
        $receipt = new ReceiptComposer()->payment('p@example.test', [new ReceiptLine(true, str_repeat('Ф', 200), 1, 100, 100), new ReceiptLine(false, '   ', 1, 200, 200)], 300, $rules);

        self::assertSame(128, mb_strlen($receipt->items[0]->description));
        self::assertSame('Фотография', $receipt->items[1]->description);
    }

    public function testMismatchAndTooManyItemsNeverReachTheProvider(): void
    {
        $rules = new ReceiptRules(1, PaymentSubjectEnum::COMMODITY, PaymentSubjectEnum::COMMODITY);
        $many = [];
        for ($i = 1; $i <= ReceiptComposer::MAX_ITEMS + 1; ++$i) {
            $many[] = new ReceiptLine(true, 'Формат ' . $i, 1, 100, 100);
        }
        foreach ([
            'RECEIPT_TOO_LARGE' => [$many, 8100],
            'RECEIPT_MISMATCH' => [[new ReceiptLine(true, 'Печать', 1, 100, 100)], 200],
        ] as $code => [$lines, $total]) {
            try {
                new ReceiptComposer()->payment('p@example.test', $lines, $total, $rules);
                self::fail('A receipt was composed: ' . $code);
            } catch (HttpException $error) {
                self::assertSame([$code, 409], [$error->getMessage(), $error->getCode()]);
            }
        }
    }

    public function testVatCodeOutsideTheProviderListIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ReceiptRules(13, PaymentSubjectEnum::COMMODITY, PaymentSubjectEnum::COMMODITY);
    }
}
