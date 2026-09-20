<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Service;

use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Contracts\Organization\Dto\GroupReferenceOutputDto;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class GroupReference implements GroupReferenceInterface
{
    public function __construct(private StructureRepository $structures) {}

    public function get(string $groupId): GroupReferenceOutputDto
    {
        try {
            $id = new StructureId($groupId);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpException('NOT_FOUND', 404, $exception);
        }
        $row = $this->structures->group($id)->fetch();
        if (false === $row) {
            throw new HttpException('NOT_FOUND', 404);
        }

        return new GroupReferenceOutputDto(
            nativeId: (int)$row['ID'],
            id: (string)$row['UF_PUBLIC_ID'],
            shootId: (string)$row['SHOOT_PUBLIC_ID'],
            groupKind: (string)$row['UF_KIND'],
        );
    }
}
