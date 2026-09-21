<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Gallery\Service;

/** Определяет доступность витрины по фактической передаче и сроку закрытия группы.
 * На границе closesAt просмотр остаётся доступен, а новая покупка запрещается.
 */
final readonly class GalleryAvailability
{
    public function state(?string $sentAt, ?string $closesAt, \DateTimeImmutable $now): string
    {
        if (null === $sentAt || null === $closesAt) {
            return 'preparing';
        }
        $utc = new \DateTimeZone('UTC');
        if (new \DateTimeImmutable($sentAt, $utc) > $now) {
            return 'preparing';
        }

        return new \DateTimeImmutable($closesAt, $utc) <= $now ? 'closed' : 'open';
    }
}
