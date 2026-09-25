<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Assignment\Service;

use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Access\Dto\InstitutionAssignmentOutputDto;
use Rebit\Share\Contracts\Access\Dto\InstitutionScopeOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Отдаёт другим модулям область учреждений сотрудника и атомарно заменяет назначения куратора и заведующего.
 * Замена сверяет подпись назначений, повышает ревизии затронутых профилей и отзывает их сессии; отказы доступа несут коды контракта.
 */
final readonly class InstitutionAccess implements InstitutionAccessInterface
{
    public function __construct(
        private InstitutionAssignmentRepository $assignments,
        private StaffProfileRepository $profiles,
        private StaffAuthorization $authorization,
        private IdentityGatewayInterface $identities,
        private TokenResolverInterface $tokens,
    ) {}

    public function lockState(): string
    {
        return 'a' . $this->assignments->revision(true);
    }

    public function signature(): string
    {
        return 'a' . $this->assignments->revision();
    }

    public function scope(int $userId): InstitutionScopeOutputDto
    {
        $profile = $this->authorization->context($userId)->profile;
        if (!in_array($profile->role->value, ['organizer', 'curator', 'head'], true)) {
            throw new HttpException('FORBIDDEN', 403);
        }

        return new InstitutionScopeOutputDto($profile->role->value, $profile->accessRevision, $this->assignments->institutionIds($userId, $profile->role->value));
    }

    public function assignments(array $institutionIds): array
    {
        $result = $this->assignments->assignments($institutionIds);
        /** @var array<int, InstitutionAssignmentOutputDto> $map */
        $map = [];
        while (false !== ($row = $result->fetch())) {
            $id = (int)$row['UF_INSTITUTION_ID'];
            $old = $map[$id] ?? new InstitutionAssignmentOutputDto();
            $curator = 'curator' === $row['UF_ROLE'];
            $head = 'head' === $row['UF_ROLE'];
            $name = null === ($row['USER_NAME'] ?? null) || '' === trim((string)$row['USER_NAME']) ? null : trim((string)$row['USER_NAME']);
            $map[$id] = new InstitutionAssignmentOutputDto(
                curatorId: $curator ? (int)$row['UF_USER_ID'] : $old->curatorId,
                headId: $head ? (int)$row['UF_USER_ID'] : $old->headId,
                curatorName: $curator ? $name : $old->curatorName,
                headName: $head ? $name : $old->headName,
            );
        }

        return $map;
    }

    public function lockParticipants(int $actorUserId, string $bearer, array $userIds): void
    {
        $userIds[] = $actorUserId;
        $userIds = array_values(array_unique($userIds));
        sort($userIds, SORT_NUMERIC);
        $this->assignments->lockProfiles($userIds);
        foreach ($userIds as $userId) {
            $this->identities->lockActive($userId);
        }
        if ($actorUserId !== $this->tokens->resolveUserId($bearer)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }
        $context = $this->authorization->context($actorUserId);
        if ('organizer' !== $context->profile->role->value) {
            throw new HttpException('FORBIDDEN', 403);
        }
    }

    public function replace(int $institutionId, InstitutionAssignmentOutputDto $desired, ?string $expectedSignature, bool $replaceOccupied, int $actorUserId, string $operationId): string
    {
        $old = $this->assignments([$institutionId])[$institutionId] ?? new InstitutionAssignmentOutputDto();
        if ($old->curatorId === $desired->curatorId && $old->headId === $desired->headId) {
            return $this->signature();
        }
        if ($this->signature() !== $expectedSignature) {
            throw new HttpException('ASSIGNMENTS_CHANGED', 409);
        }
        $affected = [];
        /** @var list<array{
         *     institutionId: int,
         *     role: string,
         *     fromUserId: null|int,
         *     toUserId: null|int,
         * }> $delta */
        $delta = [];
        foreach (['curator', 'head'] as $role) {
            $field = $role . 'Id';
            $from = $old->{$field};
            $to = $desired->{$field};
            if ($from === $to) {
                continue;
            }
            if (null !== $from && !$replaceOccupied) {
                throw new HttpException('ASSIGNMENT_OCCUPIED', 409);
            }
            if (null !== $to) {
                $profile = StaffProfile::fromRow($this->profiles->findByUserId($to)->fetch());
                if (null === $profile || !$profile->isEnabled() || $role !== $profile->role->value || null === $this->identities->findActive($to)) {
                    throw new HttpException('INVALID_ASSIGNEE', 422);
                }
                $affected[$to] = true;
            }
            if (null !== $from) {
                $affected[$from] = true;
            }
            $delta[] = ['institutionId' => $institutionId, 'role' => $role, 'fromUserId' => $from, 'toUserId' => $to];
        }
        foreach (['curator', 'head'] as $role) {
            $field = $role . 'Id';
            if ($old->{$field} !== $desired->{$field}) {
                $this->assignments->set($institutionId, $role, $desired->{$field});
            }
        }
        $ids = array_keys($affected);
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $userId) {
            $profile = StaffProfile::fromRow($this->profiles->findByUserId($userId)->fetch());
            if (null === $profile || 2147483647 <= $profile->revision || 2147483647 <= $profile->accessRevision) {
                throw new HttpException('STAFF_VERSION_UNAVAILABLE', 409);
            }
            $this->assignments->advanceUser($userId, $profile->revision, $actorUserId, $operationId, json_encode($delta, JSON_THROW_ON_ERROR));
            $this->identities->revokeSessions($userId);
        }
        $this->assignments->advanceState();

        return $this->signature();
    }
}
