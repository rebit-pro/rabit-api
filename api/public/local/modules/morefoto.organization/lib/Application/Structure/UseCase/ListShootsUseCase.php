<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\UseCase;

use Morefoto\Organization\Application\Structure\Dto\StructurePageInputDto;
use Morefoto\Organization\Application\Structure\Dto\StructurePageOutputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootOutputDto;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class ListShootsUseCase
{
    public function __construct(private StructureRepository $structure, private InstitutionAccessInterface $access, private TokenResolverInterface $tokens) {}

    public function execute(int $actor, string $bearer, StructureId $institutionId, StructurePageInputDto $input): StructurePageOutputDto
    {
        $scope = $this->access->scope($actor);
        if (!in_array($scope->role, ['organizer', 'curator', 'head'], true)) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $signature = $this->access->signature();
        $ids = 'organizer' === $scope->role ? null : $scope->institutionIds;
        $institution = $this->structure->institution($institutionId, $ids)->fetch();
        if (false === $institution) {
            throw new HttpException('NOT_FOUND', 404);
        }
        $result = $this->structure->shoots((int)$institution['ID'], $input->pageSize, $input->offset(), $ids);
        $items = [];
        $total = 0;
        while (false !== ($row = $result->fetch())) {
            $total = (int)$row['TOTAL'];
            if (null !== $row['ID']) {
                $items[] = new ShootOutputDto((string)$row['UF_PUBLIC_ID'], $institutionId->value, (string)$row['UF_NAME'], null === $row['UF_DATE'] ? null : (string)$row['UF_DATE'], (int)$row['UF_REVISION']);
            }
        }
        $current = $this->access->scope($actor);
        if ($actor !== $this->tokens->resolveUserId($bearer)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }
        if ($scope->accessRevision !== $current->accessRevision || $scope->role !== $current->role || $signature !== $this->access->signature()) {
            throw new HttpException('ACCESS_CHANGED', 409);
        }

        return new StructurePageOutputDto($items, ['page' => $input->page, 'pageSize' => $input->pageSize, 'total' => $total, 'totalPages' => (int)ceil($total / $input->pageSize)]);
    }
}
