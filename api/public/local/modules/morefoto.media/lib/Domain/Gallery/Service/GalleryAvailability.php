<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Gallery\Service;

/** Определяет доступность витрины по фактической передаче и двум срокам продаж группы (E7).
 * До closesAt продаются печать и электронные фото, до digitalSalesUntil — только электронные; просмотр остаётся и после.
 */
final readonly class GalleryAvailability
{
    public function state(?string $sentAt, ?string $closesAt, ?string $digitalSalesUntil, \DateTimeImmutable $now): string
    {
        if (null === $sentAt || null === $closesAt || null === $digitalSalesUntil) {
            return 'preparing';
        }
        $utc = new \DateTimeZone('UTC');
        if (new \DateTimeImmutable($sentAt, $utc) > $now) {
            return 'preparing';
        }

        return match (true) {
            new \DateTimeImmutable($digitalSalesUntil, $utc) <= $now => 'closed',
            new \DateTimeImmutable($closesAt, $utc) <= $now => 'digital',
            default => 'open',
        };
    }
}
