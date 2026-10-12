<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Service;

use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;

/**
 * Решает, кто и когда видит и правит разметку группы: до передачи ссылки — организатор (`media.manage`), после передачи —
 * организатор или куратор учреждения (`media.correct`), причём править можно только во время исправления разметки D4,
 * когда продажа на паузе.
 */
final readonly class GroupMarkupAccess
{
    public function __construct(private AccessGuardInterface $access) {}

    public function assertCan(int $actorId, MediaScopeOutputDto $scope): void
    {
        $this->access->assertCan($actorId, $scope->groupCorrecting ? 'media.correct' : 'media.manage', $scope->institutionId, $scope->groupId);
    }

    /** A transmitted group is already shown to the curator by the gallery link; preparation material stays with the organizer. */
    public function assertCanRead(int $actorId, MediaScopeOutputDto $scope): void
    {
        $transmitted = null !== $scope->groupId && !$scope->groupEditable;
        $this->access->assertCan($actorId, $transmitted ? 'media.correct' : 'media.manage', $scope->institutionId, $scope->groupId);
    }

    /** Re-checked after the shoot revision lock: a finished correction closes the group again. */
    public function editable(MediaScopeOutputDto $scope): bool
    {
        return $scope->groupEditable || $scope->groupCorrecting;
    }
}
