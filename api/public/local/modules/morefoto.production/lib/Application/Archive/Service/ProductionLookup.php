<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Service;

use Morefoto\Production\Application\Archive\Dto\ProductionScopeOutputDto;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Находит группу или версию архива в области сотрудника: чужое учреждение отвечает так же, как несуществующий объект,
 * чтобы куратор не узнавал о чужих группах. Срок печати группы приводится к UTC — в нём хранятся версии.
 */
final readonly class ProductionLookup
{
    public function __construct(
        private GroupDirectoryInterface $groups,
        private ArchiveRepositoryInterface $archives,
    ) {}

    public function group(ProductionScopeOutputDto $scope, string $groupId): GroupDirectoryItemOutputDto
    {
        $group = $this->groups->find($groupId);
        if (null === $group || !$this->visible($scope, $group->institutionNativeId)) {
            throw new HttpException('GROUP_NOT_FOUND', 404);
        }

        return $group;
    }

    public function version(ProductionScopeOutputDto $scope, string $versionId): ArchiveVersion
    {
        $version = $this->archives->find($versionId);
        if (null === $version || !$this->visible($scope, $version->institutionId)) {
            throw new HttpException('VERSION_NOT_FOUND', 404);
        }

        return $version;
    }

    public function visible(ProductionScopeOutputDto $scope, int $institutionId): bool
    {
        return null === $scope->institutionIds || in_array($institutionId, $scope->institutionIds, true);
    }

    /** Срок приёма печати в UTC; null — ссылка группы ещё не передана. */
    public function deadline(GroupDirectoryItemOutputDto $group): ?\DateTimeImmutable
    {
        if (null === $group->calendar->sentAt || null === $group->calendar->closesAt) {
            return null;
        }

        return new \DateTimeImmutable($group->calendar->closesAt)->setTimezone(new \DateTimeZone('UTC'));
    }
}
