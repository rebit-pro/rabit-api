<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Service;

use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;

/**
 * Решает, кто и когда правит разметку группы: до передачи ссылки — организатор (`media.manage`), после передачи — только
 * во время исправления разметки D4, когда продажа на паузе, организатор или куратор учреждения (`media.correct`).
 */
final readonly class GroupMarkupAccess
{
    public function __construct(private AccessGuardInterface $access) {}

    public function assertCan(int $actorId, MediaScopeOutputDto $scope): void
    {
        $this->access->assertCan($actorId, $scope->groupCorrecting ? 'media.correct' : 'media.manage', $scope->institutionId, $scope->groupId);
    }

    /** Re-checked after the shoot revision lock: a finished correction closes the group again. */
    public function editable(MediaScopeOutputDto $scope): bool
    {
        return $scope->groupEditable || $scope->groupCorrecting;
    }
}
