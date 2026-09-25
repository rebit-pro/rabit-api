<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Application\Calendar\Service\CalendarProjection;
use Morefoto\Organization\Domain\Calendar\Service\CalendarPolicy;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use Morefoto\Organization\Infrastructure\Calendar\ServerCalendarClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class GroupCalendarTest extends TestCase
{
    public function testFirstDeliveryUsesExactLocalTimeAndBoundaryCloses(): void
    {
        $sent = new \DateTimeImmutable('2026-09-11T14:30:00+03:00');
        $empty = new GroupCalendar();
        self::assertSame('preparing', $empty->status($sent));
        $calendar = $empty->confirmLinkSent($sent);
        self::assertSame('2026-09-18T14:30:00+03:00', $calendar->closesAt?->format(\DateTimeInterface::ATOM));
        self::assertSame('2026-09-25T14:30:00+03:00', $calendar->deliveryDueAt?->format(\DateTimeInterface::ATOM));
        self::assertSame('open', $calendar->status(new \DateTimeImmutable('2026-09-18T14:29:59+03:00')));
        self::assertSame('closed', $calendar->status(new \DateTimeImmutable('2026-09-18T14:30:00+03:00')));
        self::assertSame('closed', $calendar->status(new \DateTimeImmutable('2026-09-19T00:00:00Z')));
    }

    public function testRetransmissionAfterClosePreservesAllDatesAndInstance(): void
    {
        $calendar = (new GroupCalendar())->confirmLinkSent(new \DateTimeImmutable('2026-09-11T11:30:00Z'));
        self::assertSame($calendar, $calendar->confirmLinkSent(new \DateTimeImmutable('2026-10-10T00:00:00Z')));
    }

    public function testExtensionKeepsFirstDeliveryAndMovesDeliveryFromNewClose(): void
    {
        $calendar = (new GroupCalendar())->confirmLinkSent(new \DateTimeImmutable('2026-12-24T14:30:00+03:00'));
        $newClose = new \DateTimeImmutable('2027-01-05T20:15:00+03:00');
        $extended = $calendar->extend($newClose, new \DateTimeImmutable('2027-01-01T00:00:00Z'));
        self::assertSame($calendar->sentAt, $extended->sentAt);
        self::assertSame($newClose, $extended->closesAt);
        self::assertSame('2027-01-12T20:15:00+03:00', $extended->deliveryDueAt?->format(\DateTimeInterface::ATOM));
        self::assertSame('open', $extended->status(new \DateTimeImmutable('2027-01-01T00:00:00Z')));
    }

    #[DataProvider('invalidExtensions')]
    public function testExtensionRequiresStrictlyLaterThanCloseAndNow(string $close, string $now): void
    {
        $calendar = (new GroupCalendar())->confirmLinkSent(new \DateTimeImmutable('2026-09-11T14:30:00+03:00'));
        $this->expectException(\DomainException::class);
        $calendar->extend(new \DateTimeImmutable($close), new \DateTimeImmutable($now));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidExtensions(): iterable
    {
        yield 'same deadline' => ['2026-09-18T14:30:00+03:00', '2026-09-12T00:00:00Z'];
        yield 'shorter deadline' => ['2026-09-17T14:30:00+03:00', '2026-09-12T00:00:00Z'];
        yield 'equal to server now' => ['2026-09-20T14:30:00+03:00', '2026-09-20T14:30:00+03:00'];
        yield 'later than previous but past' => ['2026-09-20T14:30:00+03:00', '2026-09-21T00:00:00Z'];
    }

    public function testUnsentGroupCannotBeExtended(): void
    {
        $this->expectException(\DomainException::class);
        (new GroupCalendar())->extend(new \DateTimeImmutable('2026-09-30T00:00:00Z'), new \DateTimeImmutable('2026-09-20T00:00:00Z'));
    }

    #[DataProvider('monthBoundaries')]
    public function testMonthIsClampedCalendarMonth(string $instant, string $expected): void
    {
        self::assertSame($expected, CalendarPolicy::addMonthClamped(new \DateTimeImmutable($instant))->format(\DateTimeInterface::ATOM));
    }

    /** @return iterable<string, array{string, string}> */
    public static function monthBoundaries(): iterable
    {
        yield 'January common year' => ['2027-01-31T10:00:00+03:00', '2027-02-28T10:00:00+03:00'];
        yield 'January leap year' => ['2028-01-31T10:00:00+03:00', '2028-02-29T10:00:00+03:00'];
        yield 'leap day' => ['2028-02-29T10:00:00+03:00', '2028-03-29T10:00:00+03:00'];
        yield 'thirty-day month' => ['2026-03-31T10:00:00+03:00', '2026-04-30T10:00:00+03:00'];
        yield 'year rollover' => ['2026-12-31T10:00:00+03:00', '2027-01-31T10:00:00+03:00'];
        yield 'UTC already next Moscow date' => ['2027-01-30T22:15:00Z', '2027-02-28T01:15:00+03:00'];
        yield 'month is not thirty days' => ['2027-02-01T10:00:00+03:00', '2027-03-01T10:00:00+03:00'];
    }

    public function testCalendarDaysPreserveLocalClockAcrossDst(): void
    {
        $before = new \DateTimeImmutable('2026-03-22T14:30:00+01:00');
        $after = CalendarPolicy::addDays($before, 7, 'Europe/Berlin');
        self::assertSame('2026-03-29T14:30:00+02:00', $after->format(\DateTimeInterface::ATOM));
        self::assertSame(167 * 3600, $after->getTimestamp() - $before->getTimestamp());
    }

    public function testMoscowGroupDoesNotAcceptAnotherBusinessZone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GroupCalendar(timezone: 'Europe/Berlin');
    }

    public function testStorageUsesUtcAndProjectionUsesExplicitMoscowOffset(): void
    {
        $calendar = GroupCalendar::fromStorage('2026-09-11 11:30:00', '2026-09-18 11:30:00', '2026-09-25 11:30:00');
        $projection = CalendarProjection::create($calendar, new \DateTimeImmutable('2026-09-18T11:30:00Z'));
        self::assertSame('UTC', $calendar->sentAt?->getTimezone()->getName());
        self::assertSame('Europe/Moscow', $projection->timezone);
        self::assertSame('2026-09-11T14:30:00+03:00', $projection->sentAt);
        self::assertSame('2026-09-18T14:30:00+03:00', $projection->closesAt);
        self::assertSame('2026-09-25T14:30:00+03:00', $projection->deliveryDueAt);
        self::assertSame('closed', $projection->status);
    }

    #[DataProvider('invalidStorage')]
    public function testRejectsCorruptOrPartialStorage(?string $sent, ?string $close, ?string $delivery): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GroupCalendar::fromStorage($sent, $close, $delivery);
    }

    /** @return iterable<string, array{?string, ?string, ?string}> */
    public static function invalidStorage(): iterable
    {
        yield 'sent only' => ['2026-09-11 11:30:00', null, null];
        yield 'close only' => [null, '2026-09-18 11:30:00', null];
        yield 'delivery only' => [null, null, '2026-09-25 11:30:00'];
        yield 'missing delivery' => ['2026-09-11 11:30:00', '2026-09-18 11:30:00', null];
        yield 'invalid leap date' => ['2027-02-29 11:30:00', '2027-03-07 11:30:00', '2027-03-14 11:30:00'];
        yield 'offset in UTC storage' => ['2026-09-11T11:30:00Z', '2026-09-18 11:30:00', '2026-09-25 11:30:00'];
        yield 'close before send' => ['2026-09-19 11:30:00', '2026-09-18 11:30:00', '2026-09-25 11:30:00'];
        yield 'delivery before close' => ['2026-09-11 11:30:00', '2026-09-18 11:30:00', '2026-09-17 11:30:00'];
        yield 'close equals send' => ['2026-09-11 11:30:00', '2026-09-11 11:30:00', '2026-09-25 11:30:00'];
    }

    public function testExtensionCannotOverflowThePersistedDeliveryDate(): void
    {
        $calendar = (new GroupCalendar())->confirmLinkSent(new \DateTimeImmutable('9999-12-01T00:00:00Z'));
        $this->expectException(\InvalidArgumentException::class);
        $calendar->extend(new \DateTimeImmutable('9999-12-29T00:00:00Z'), new \DateTimeImmutable('9999-12-02T00:00:00Z'));
    }

    public function testSubsecondConfirmationCannotBeSilentlyTruncated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new GroupCalendar())->confirmLinkSent(new \DateTimeImmutable('2026-09-11T14:30:00.123456+03:00'));
    }

    public function testServerClockFitsUtcSecondPrecisionStorage(): void
    {
        $before = time();
        $now = (new ServerCalendarClock())->now();
        self::assertGreaterThanOrEqual($before, $now->getTimestamp());
        self::assertLessThanOrEqual(time(), $now->getTimestamp());
        self::assertSame('000000', $now->format('u'));
        self::assertSame('UTC', $now->getTimezone()->getName());
    }
}
