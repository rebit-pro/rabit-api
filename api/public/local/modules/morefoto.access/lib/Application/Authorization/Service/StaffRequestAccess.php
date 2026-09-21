<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Authorization\Service;

use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;

/**
 * Передаёт Handoff только подтверждённую сервером роль и актуальную область сотрудника.
 *
 * Повторно читает Access на каждый запрос, поэтому отозванная роль или назначение не остаются в singleton-сервисе.
 */
final readonly class StaffRequestAccess implements StaffRequestAccessInterface
{
    public function __construct(private StaffAuthorization $authorization) {}

    public function actor(int $userId): StaffRequestActorOutputDto
    {
        $context = $this->authorization->context($userId);

        return new StaffRequestActorOutputDto(
            id: $context->identity->id,
            name: $context->identity->name,
            role: $context->profile->role->value,
            accessRevision: $context->profile->accessRevision,
            institutionIds: $this->authorization->institutionIds($context->profile),
            groupIds: $this->authorization->groupIds($context->profile),
        );
    }
}
