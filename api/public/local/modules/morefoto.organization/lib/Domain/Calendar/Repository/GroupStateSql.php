<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Calendar\Repository;

/**
 * SQL form of GroupCalendar::status(): preparing until the link is sent, closed from the closing moment, open in
 * between. Filters, pages and counters of group states all use it, so a list and its summary never disagree.
 */
final readonly class GroupStateSql
{
    public const array STATES = ['preparing', 'open', 'closed'];

    /** @param string $nowUtc server time as `Y-m-d H:i:s` in UTC, the storage zone of group moments */
    public static function condition(string $state, string $nowUtc, string $alias = 'g'): string
    {
        $now = self::moment($nowUtc);

        return match ($state) {
            'preparing' => "{$alias}.UF_SENT_AT IS NULL",
            'open' => "{$alias}.UF_SENT_AT IS NOT NULL AND {$alias}.UF_CLOSES_AT>'{$now}'",
            'closed' => "{$alias}.UF_SENT_AT IS NOT NULL AND {$alias}.UF_CLOSES_AT<='{$now}'",
            default => throw new \InvalidArgumentException('Unsupported group state.'),
        };
    }

    /** Conditional sums STATE_PREPARING, STATE_OPEN and STATE_CLOSED for an aggregate over groups. */
    public static function counters(string $nowUtc, string $alias = 'g'): string
    {
        $sums = [];
        foreach (self::STATES as $state) {
            $sums[] = sprintf('COALESCE(SUM(%s),0) AS STATE_%s', self::condition($state, $nowUtc, $alias), strtoupper($state));
        }

        return implode(',', $sums);
    }

    /**
     * @param array<string, mixed> $row a row with the counters() columns
     *
     * @return array{preparing: int, open: int, closed: int}
     */
    public static function byState(array $row): array
    {
        return [
            'preparing' => (int)($row['STATE_PREPARING'] ?? 0),
            'open' => (int)($row['STATE_OPEN'] ?? 0),
            'closed' => (int)($row['STATE_CLOSED'] ?? 0),
        ];
    }

    public static function moment(string $nowUtc): string
    {
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $nowUtc)) {
            throw new \InvalidArgumentException('Group state moment must be Y-m-d H:i:s.');
        }

        return $nowUtc;
    }

    public static function utc(\DateTimeImmutable $now): string
    {
        return $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
