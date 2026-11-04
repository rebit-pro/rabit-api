<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Service;

use Morefoto\Handoff\Domain\Link\Repository\OpeningScheduleRepositoryInterface;
use Rebit\Share\Contracts\Handoff\Dto\GalleryOpeningOutputDto;
use Rebit\Share\Contracts\Handoff\GalleryOpeningInterface;

/** Сообщает публичной галерее назначенное открытие без раскрытия служебной истории плана. */
final readonly class GalleryOpeningReader implements GalleryOpeningInterface
{
    public function __construct(private OpeningScheduleRepositoryInterface $schedules) {}

    public function current(string $groupId): ?GalleryOpeningOutputDto
    {
        $schedule = $this->schedules->findByPublicId($groupId);

        return null === $schedule ? null : new GalleryOpeningOutputDto($schedule->status->value, $schedule->openAt->format(\DateTimeInterface::ATOM));
    }
}
