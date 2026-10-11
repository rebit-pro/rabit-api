<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Domain\Calendar\Exception\CalendarRuleViolation;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class GroupCalendarDeliveryTest extends TestCase
{
    #[DataProvider('deliveries')]
    public function testReportedDeliveryOpensPrintForSevenAndDigitalForFourteenCalendarDaysInMoscow(string $sent, string $close, string $digital, string $delivery): void
    {
        $calendar = (new GroupCalendar())->recordLinkSent(new \DateTimeImmutable($sent), new \DateTimeImmutable('2030-01-01T00:00:00Z'));
        self::assertSame($close, $this->moscow($calendar->closesAt));
        self::assertSame($digital, $this->moscow($calendar->digitalSalesUntil));
        self::assertSame($delivery, $this->moscow($calendar->deliveryDueAt));
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function deliveries(): iterable
    {
        yield 'E7 example' => ['2026-10-11T15:00:00+03:00', '2026-10-18T15:00:00+03:00', '2026-10-25T15:00:00+03:00', '2026-10-25T15:00:00+03:00'];
        yield 'D10 example' => ['2026-09-11T14:30:00+03:00', '2026-09-18T14:30:00+03:00', '2026-09-25T14:30:00+03:00', '2026-09-25T14:30:00+03:00'];
        yield 'year rollover' => ['2026-12-29T18:12:00+03:00', '2027-01-05T18:12:00+03:00', '2027-01-12T18:12:00+03:00', '2027-01-12T18:12:00+03:00'];
        yield 'leap day' => ['2028-02-25T09:00:00+03:00', '2028-03-03T09:00:00+03:00', '2028-03-10T09:00:00+03:00', '2028-03-10T09:00:00+03:00'];
        yield 'UTC input' => ['2026-09-07T07:15:00Z', '2026-09-14T10:15:00+03:00', '2026-09-21T10:15:00+03:00', '2026-09-21T10:15:00+03:00'];
    }

    public function testFutureDeliveryIsRejectedAndRepeatedReportKeepsTheInstance(): void
    {
        $now = new \DateTimeImmutable('2026-09-22T10:00:00+03:00');
        $this->assertViolation('SENT_AT_IN_FUTURE', static fn(): GroupCalendar => (new GroupCalendar())->recordLinkSent($now->modify('+1 second'), $now));
        $calendar = (new GroupCalendar())->recordLinkSent($now, $now);
        self::assertSame($calendar, $calendar->recordLinkSent($now->modify('-1 day'), $now));
        self::assertSame('open', $calendar->status($now));
    }

    public function testCorrectionRecalculatesDeadlinesAndMayCloseImmediately(): void
    {
        $calendar = (new GroupCalendar())->recordLinkSent(new \DateTimeImmutable('2026-09-07T10:15:00+03:00'), new \DateTimeImmutable('2026-09-07T12:00:00+03:00'));
        $now = new \DateTimeImmutable('2026-09-08T12:00:00+03:00');
        $corrected = $calendar->correctLinkSent(new \DateTimeImmutable('2026-08-30T09:00:00+03:00'), $now);
        self::assertSame('2026-08-30T09:00:00+03:00', $this->moscow($corrected->sentAt));
        self::assertSame('2026-09-06T09:00:00+03:00', $this->moscow($corrected->closesAt));
        self::assertSame('2026-09-13T09:00:00+03:00', $this->moscow($corrected->digitalSalesUntil));
        self::assertSame('2026-09-13T09:00:00+03:00', $this->moscow($corrected->deliveryDueAt));
        self::assertSame('digital', $corrected->status($now));
        self::assertSame('digital', $corrected->status(new \DateTimeImmutable('2026-09-06T09:00:00+03:00')));
        self::assertSame('open', $corrected->status(new \DateTimeImmutable('2026-09-06T08:59:59+03:00')));
        self::assertSame('closed', $corrected->status(new \DateTimeImmutable('2026-09-13T09:00:00+03:00')));
    }

    public function testCorrectionIsRefusedOnceThePrintDeadlineHasCome(): void
    {
        $calendar = (new GroupCalendar())->recordLinkSent(new \DateTimeImmutable('2026-10-11T15:00:00+03:00'), new \DateTimeImmutable('2026-10-11T16:00:00+03:00'));
        $later = new \DateTimeImmutable('2026-10-12T15:00:00+03:00');
        self::assertSame('2026-10-19T15:00:00+03:00', $this->moscow($calendar->correctLinkSent($later, new \DateTimeImmutable('2026-10-18T14:59:59+03:00'))->closesAt));
        $this->assertViolation('PRINT_SALES_CLOSED', static fn(): GroupCalendar => $calendar->correctLinkSent($later, new \DateTimeImmutable('2026-10-18T15:00:00+03:00')));
        $this->assertViolation('PRINT_SALES_CLOSED', static fn(): GroupCalendar => $calendar->correctLinkSent($later, new \DateTimeImmutable('2026-10-21T00:00:00+03:00')));
    }

    public function testCorrectionKeepsAnAgreedExtensionWhileItIsLater(): void
    {
        $sent = new \DateTimeImmutable('2026-09-07T10:15:00+03:00');
        $extended = (new GroupCalendar())->recordLinkSent($sent, $sent)
            ->extend(new \DateTimeImmutable('2026-09-20T10:00:00+03:00'), new \DateTimeImmutable('2026-09-10T00:00:00+03:00'))
        ;
        $now = new \DateTimeImmutable('2026-09-19T00:00:00+03:00');
        $earlier = $extended->correctLinkSent(new \DateTimeImmutable('2026-09-06T10:15:00+03:00'), $now);
        self::assertSame('2026-09-20T10:00:00+03:00', $this->moscow($earlier->closesAt));
        self::assertSame('2026-09-20T10:15:00+03:00', $this->moscow($earlier->digitalSalesUntil));
        self::assertSame('2026-09-27T10:00:00+03:00', $this->moscow($earlier->deliveryDueAt));
        $later = $extended->correctLinkSent(new \DateTimeImmutable('2026-09-14T12:00:00+03:00'), $now);
        self::assertSame('2026-09-21T12:00:00+03:00', $this->moscow($later->closesAt));
        self::assertSame('2026-09-28T12:00:00+03:00', $this->moscow($later->digitalSalesUntil));
        $regular = (new GroupCalendar())->recordLinkSent($sent, $sent)
            ->correctLinkSent(new \DateTimeImmutable('2026-09-05T08:00:00+03:00'), new \DateTimeImmutable('2026-09-10T00:00:00+03:00'))
        ;
        self::assertSame('2026-09-12T08:00:00+03:00', $this->moscow($regular->closesAt));
        self::assertSame('2026-09-19T08:00:00+03:00', $this->moscow($regular->digitalSalesUntil));
    }

    public function testCorrectionRequiresARecordedDifferentPastMoment(): void
    {
        $now = new \DateTimeImmutable('2026-09-22T10:00:00+03:00');
        $sent = new \DateTimeImmutable('2026-09-21T10:00:00+03:00');
        $this->assertViolation('LINK_NOT_SENT', static fn(): GroupCalendar => (new GroupCalendar())->correctLinkSent($sent, $now));
        $calendar = (new GroupCalendar())->recordLinkSent($sent, $now);
        $this->assertViolation('SENT_AT_UNCHANGED', static fn(): GroupCalendar => $calendar->correctLinkSent(new \DateTimeImmutable('2026-09-21T07:00:00Z'), $now));
        $this->assertViolation('SENT_AT_IN_FUTURE', static fn(): GroupCalendar => $calendar->correctLinkSent($now->modify('+1 minute'), $now));
    }

    private function moscow(?\DateTimeImmutable $moment): ?string
    {
        return $moment?->setTimezone(new \DateTimeZone('Europe/Moscow'))->format(\DateTimeInterface::ATOM);
    }

    /** @param callable(): GroupCalendar $operation */
    private function assertViolation(string $code, callable $operation): void
    {
        try {
            $operation();
            self::fail('Calendar rule violation was expected: ' . $code);
        } catch (CalendarRuleViolation $violation) {
            self::assertSame($code, $violation->getMessage());
        }
    }
}
