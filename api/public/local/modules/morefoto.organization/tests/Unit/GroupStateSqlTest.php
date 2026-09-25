<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Domain\Calendar\Repository\GroupStateSql;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class GroupStateSqlTest extends TestCase
{
    public function testSqlFollowsTheCalendarStatusAroundTheClosingMoment(): void
    {
        $closing = new \DateTimeImmutable('2026-09-29 07:15:00', new \DateTimeZone('UTC'));
        $sent = GroupCalendar::fromStorage('2026-09-22 07:15:00', '2026-09-29 07:15:00', '2026-10-06 07:15:00');

        self::assertSame('open', $sent->status($closing->modify('-1 second')));
        self::assertSame("g.UF_SENT_AT IS NOT NULL AND g.UF_CLOSES_AT>'2026-09-29 07:15:00'", GroupStateSql::condition('open', GroupStateSql::utc($closing)));
        self::assertSame('closed', $sent->status($closing));
        self::assertSame("g.UF_SENT_AT IS NOT NULL AND g.UF_CLOSES_AT<='2026-09-29 07:15:00'", GroupStateSql::condition('closed', GroupStateSql::utc($closing)));
        self::assertSame('preparing', GroupCalendar::fromStorage(null, null, null)->status($closing));
        self::assertSame('s.UF_SENT_AT IS NULL', GroupStateSql::condition('preparing', '2026-09-29 07:15:00', 's'));
    }

    public function testCountersReadEveryState(): void
    {
        self::assertSame(['preparing' => 2, 'open' => 5, 'closed' => 1], GroupStateSql::byState(['STATE_PREPARING' => '2', 'STATE_OPEN' => '5', 'STATE_CLOSED' => '1']));
        self::assertSame(['preparing' => 0, 'open' => 0, 'closed' => 0], GroupStateSql::byState([]));
        self::assertStringContainsString('AS STATE_CLOSED', GroupStateSql::counters('2026-09-29 07:15:00'));
    }

    public function testRejectsAnythingButAServerMoment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GroupStateSql::condition('open', "2026-09-29' OR '1'='1");
    }
}
