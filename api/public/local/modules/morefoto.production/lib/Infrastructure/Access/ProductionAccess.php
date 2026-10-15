<?php

declare(strict_types=1);

namespace Morefoto\Production\Infrastructure\Access;

use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Dto\ProductionScopeOutputDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Переводит роль и назначения Access в права архива печати L1-DEC-07: организатор — всё, куратор — чтение своих учреждений. */
final readonly class ProductionAccess implements ProductionAccessInterface
{
    public function __construct(private InstitutionAccessInterface $institutions) {}

    public function scope(int $actorId): ProductionScopeOutputDto
    {
        $scope = $this->institutions->scope($actorId);
        $institutionIds = array_values(array_unique(array_map('intval', $scope->institutionIds)));
        sort($institutionIds);

        return match ($scope->role) {
            'organizer' => new ProductionScopeOutputDto($scope->role, null, true),
            'curator' => new ProductionScopeOutputDto($scope->role, $institutionIds, false),
            default => throw new HttpException('FORBIDDEN', 403),
        };
    }
}
