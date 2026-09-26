<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Service;

/** Задаёт бизнес-время заказа по Москве: срок личного ключа, срок файлов D10 и границы календарных дней для поиска.
 * Моменты хранятся в UTC, а календарные правила D07/D10 считаются в Europe/Moscow.
 */
final readonly class OrderCalendarPolicy
{
    public const string TIMEZONE = 'Europe/Moscow';
    private const string KEY_LIFETIME = '+30 days';

    public function keyExpiresAt(\DateTimeImmutable $issuedAt): \DateTimeImmutable
    {
        return $issuedAt->setTimezone(new \DateTimeZone(self::TIMEZONE))->modify(self::KEY_LIFETIME)->setTimezone(new \DateTimeZone('UTC'));
    }

    /** D10: файлы доступны календарный месяц от первой оплаты по Москве; 31-е число переходит на последний день короткого месяца. */
    public function filesAvailableUntil(\DateTimeImmutable $paidAt): \DateTimeImmutable
    {
        $local = $paidAt->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $next = $local->modify('first day of next month');
        $day = min((int)$local->format('j'), (int)$next->format('t'));

        return $next->setDate((int)$next->format('Y'), (int)$next->format('n'), $day)->setTimezone(new \DateTimeZone('UTC'));
    }

    /** Начало календарного дня YYYY-MM-DD по Москве в UTC; с $nextDay — начало следующего дня. */
    public function dayStart(string $date, bool $nextDay = false): \DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone(self::TIMEZONE));
        if (false === $day || $day->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Invalid calendar date.');
        }

        return ($nextDay ? $day->modify('+1 day') : $day)->setTimezone(new \DateTimeZone('UTC'));
    }

    public function display(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone(self::TIMEZONE))->format(DATE_ATOM);
    }
}
