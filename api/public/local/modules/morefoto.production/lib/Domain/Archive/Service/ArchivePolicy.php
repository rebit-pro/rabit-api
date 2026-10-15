<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\Service;

/**
 * Сроки архива печати по решениям L1: сборка через час после закрытия печати, чтобы сверка G1 успела подтвердить
 * оплаты последних минут (L1-DEC-01), и одинаковый срок хранения файла и действия ссылки — 14 дней (L1-DEC-03/04).
 */
final readonly class ArchivePolicy
{
    public const int MAX_ATTEMPTS = 3;
    private const string BUILD_DELAY = 'PT1H';
    private const string RETENTION = 'P14D';
    private const string LEASE = 'PT30M';
    private const string RETRY = 'PT5M';

    /** Группы со сроком печати не позже этого момента пора собирать. */
    public function cutoff(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->sub(new \DateInterval(self::BUILD_DELAY));
    }

    public function buildFrom(\DateTimeImmutable $deadline): \DateTimeImmutable
    {
        return $deadline->add(new \DateInterval(self::BUILD_DELAY));
    }

    public function expiresAt(\DateTimeImmutable $readyAt): \DateTimeImmutable
    {
        return $readyAt->add(new \DateInterval(self::RETENTION));
    }

    /** Сборка большого архива занимает минуты; за полчаса упавший процесс точно не держит аренду. */
    public function leaseUntil(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->add(new \DateInterval(self::LEASE));
    }

    public function retryAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->add(new \DateInterval(self::RETRY));
    }
}
