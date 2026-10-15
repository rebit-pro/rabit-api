<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\Service;

use Morefoto\Production\Domain\Archive\Enum\GroupProductionStateEnum;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;

/**
 * Сводит календарь группы и её последнюю версию в одно состояние блока «Печать». Группа, печать которой закрылась
 * до выкладки L1, автоматически не собирается и ждёт кнопки организатора (L1-DEC-08).
 */
final readonly class ProductionStatePolicy
{
    public function state(?\DateTimeImmutable $deadline, ?ArchiveVersion $latest, \DateTimeImmutable $since, \DateTimeImmutable $now): GroupProductionStateEnum
    {
        if (null !== $latest) {
            return match ($latest->status) {
                VersionStatusEnum::PENDING => GroupProductionStateEnum::BUILDING,
                VersionStatusEnum::READY => GroupProductionStateEnum::READY,
                VersionStatusEnum::FAILED => GroupProductionStateEnum::FAILED,
                VersionStatusEnum::EMPTY => GroupProductionStateEnum::EMPTY,
                VersionStatusEnum::EXPIRED => GroupProductionStateEnum::EXPIRED,
            };
        }
        if (null === $deadline || $deadline > $now) {
            return GroupProductionStateEnum::OPEN;
        }

        return $deadline > $since ? GroupProductionStateEnum::SCHEDULED : GroupProductionStateEnum::MANUAL;
    }

    /** Печать группы закрыта: можно собирать архив или дополнение. */
    public function closed(?\DateTimeImmutable $deadline, \DateTimeImmutable $now): bool
    {
        return null !== $deadline && $deadline <= $now;
    }
}
