<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Authorization\Service;

use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Contracts\Access\Dto\LinkActorOutputDto;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Передаёт сценарию ссылок группы только подтверждённые сервером роль и назначения сотрудника.
 *
 * Для команд соблюдает общий порядок блокировок Access → Organization → профиль/учётная запись, чтобы смена назначения
 * не проходила параллельно с передачей ссылки и роль перечитывалась уже под блокировкой.
 */
final readonly class GroupLinkAccess implements GroupLinkAccessInterface
{
    public function __construct(
        private StaffAuthorization $authorization,
        private InstitutionAssignmentRepository $assignments,
        private IdentityGatewayInterface $identities,
    ) {}

    public function actor(int $userId): LinkActorOutputDto
    {
        $context = $this->authorization->context($userId);

        return new LinkActorOutputDto(
            id: $context->identity->id,
            name: $context->identity->name,
            role: $context->profile->role->value,
            institutionIds: $this->authorization->institutionIds($context->profile),
            groupIds: $this->authorization->groupIds($context->profile),
        );
    }

    public function lockState(): void
    {
        $this->assignments->revision(true);
    }

    public function lockActor(int $userId): LinkActorOutputDto
    {
        $this->assignments->lockProfiles([$userId]);
        if (null === $this->identities->lockActive($userId)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }

        return $this->actor($userId);
    }
}
