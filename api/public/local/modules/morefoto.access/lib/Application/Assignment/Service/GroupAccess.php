<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Assignment\Service;

use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class GroupAccess implements GroupAccessInterface
{
    public function __construct(
        private GroupAssignmentRepository $groups,
        private InstitutionAccessInterface $institutionAccess,
        private InstitutionAssignmentRepository $accessChanges,
        private StaffProfileRepository $profiles,
        private IdentityGatewayInterface $identities,
    ) {}

    public function lockState(): string
    {
        return $this->institutionAccess->lockState();
    }

    public function signature(): string
    {
        return $this->institutionAccess->signature();
    }

    public function assignments(array $groupIds): array
    {
        $result = $this->groups->assignments($groupIds);
        $map = [];
        while (false !== ($row = $result->fetch())) {
            $map[(int)$row['UF_GROUP_ID']] = new GroupAssignmentOutputDto((int)$row['UF_USER_ID']);
        }

        return $map;
    }

    public function lockParticipants(int $actorUserId, string $bearer, array $userIds): void
    {
        $this->institutionAccess->lockParticipants($actorUserId, $bearer, $userIds);
    }

    public function replace(int $groupId, GroupAssignmentOutputDto $desired, ?string $expectedSignature, bool $replaceOccupied, int $actorUserId, string $operationId, ?string $reason): string
    {
        if (1 > $groupId || 1 > $actorUserId || (null !== $desired->teacherId && 1 > $desired->teacherId)) {
            throw new \InvalidArgumentException('Positive group and user IDs required.');
        }
        $old = $this->assignments([$groupId])[$groupId] ?? new GroupAssignmentOutputDto();
        if ($old->teacherId === $desired->teacherId) {
            return $this->signature();
        }
        if ($this->signature() !== $expectedSignature) {
            throw new HttpException('ASSIGNMENTS_CHANGED', 409);
        }
        $reason = null === $reason ? null : trim($reason);
        if (null !== $old->teacherId) {
            if (!$replaceOccupied) {
                throw new HttpException('ASSIGNMENT_OCCUPIED', 409);
            }
            if (null === $reason || '' === $reason) {
                throw new HttpException('ASSIGNMENT_REASON_REQUIRED', 422);
            }
        }
        if (null !== $reason && 2000 < mb_strlen($reason)) {
            throw new HttpException('INVALID_ASSIGNMENT_REASON', 422);
        }
        $affected = [];
        foreach ([$old->teacherId, $desired->teacherId] as $userId) {
            if (null !== $userId) {
                $affected[] = $userId;
            }
        }
        sort($affected, SORT_NUMERIC);
        /** @var array<int, StaffProfile> $profiles */
        $profiles = [];
        foreach ($affected as $userId) {
            $profile = StaffProfile::fromRow($this->profiles->findByUserId($userId)->fetch());
            if ($userId === $desired->teacherId && (null === $profile || !$profile->isEnabled() || 'teacher' !== $profile->role->value || null === $this->identities->findActive($userId))) {
                throw new HttpException('INVALID_ASSIGNEE', 422);
            }
            if (null === $profile || 2147483647 <= $profile->revision || 2147483647 <= $profile->accessRevision) {
                throw new HttpException('STAFF_VERSION_UNAVAILABLE', 409);
            }
            $profiles[$userId] = $profile;
        }
        $delta = json_encode([
            'groupId' => $groupId,
            'role' => 'teacher',
            'fromUserId' => $old->teacherId,
            'toUserId' => $desired->teacherId,
            'reason' => $reason,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->groups->set($groupId, $desired->teacherId);
        foreach ($profiles as $userId => $profile) {
            $this->accessChanges->advanceUser($userId, $profile->revision, $actorUserId, $operationId, $delta);
            $this->identities->revokeSessions($userId);
        }
        $this->accessChanges->advanceState();

        return $this->signature();
    }
}
