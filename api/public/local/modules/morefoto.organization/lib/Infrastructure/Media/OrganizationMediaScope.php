<?php

declare(strict_types=1);

namespace Morefoto\Organization\Infrastructure\Media;

use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Contracts\Organization\Dto\MediaGroupOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class OrganizationMediaScope implements MediaScopeInterface
{
    public function __construct(private StructureRepository $structures) {}

    public function resolve(string $shootId, ?string $groupId = null): MediaScopeOutputDto
    {
        $shoot = $this->structures->shoot(new StructureId($shootId))->fetch();
        if (!is_array($shoot)) {
            throw new HttpException('SHOOT_NOT_FOUND', 404);
        }
        $groupInternalId = null;
        $groupPublicId = null;
        if (null !== $groupId) {
            $group = $this->structures->group(new StructureId($groupId))->fetch();
            if (!is_array($group) || (int)$group['UF_SHOOT_ID'] !== (int)$shoot['ID']) {
                throw new HttpException('GROUP_NOT_FOUND', 404);
            }
            $groupInternalId = (int)$group['ID'];
            $groupPublicId = (string)$group['UF_PUBLIC_ID'];
        }

        return new MediaScopeOutputDto(
            institutionId: (int)$shoot['UF_INSTITUTION_ID'],
            shootId: (int)$shoot['ID'],
            shootPublicId: (string)$shoot['UF_PUBLIC_ID'],
            groupId: $groupInternalId,
            groupPublicId: $groupPublicId,
        );
    }

    public function groups(string $shootId): array
    {
        $scope = $this->resolve($shootId);
        $result = $this->structures->groups($scope->shootId, 100, 0);
        $groups = [];
        while (false !== ($row = $result->fetch())) {
            if (null === ($row['ID'] ?? null)) {
                continue;
            }
            $groups[] = new MediaGroupOutputDto(
                id: (int)$row['ID'],
                publicId: (string)$row['UF_PUBLIC_ID'],
                name: (string)$row['UF_NAME'],
                kind: (string)$row['UF_KIND'],
            );
        }

        return $groups;
    }
}
