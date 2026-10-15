<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Dto\VersionOutputDto;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Archive\Service\VersionSnapshot;
use Morefoto\Production\Application\Archive\Service\VersionView;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ProductionStatePolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * PRD-03: организатор вручную фиксирует версию архива закрытой группы — первую для группы, закрывшейся до выкладки
 * L1 (L1-DEC-08), или дополнение с оплатами, подтверждёнными после прежней сборки (L1-DEC-02). Собранные версии не
 * меняются; повтор запроса с тем же Idempotency-Key возвращает ту же версию.
 */
final readonly class CreateProductionVersionUseCase
{
    public function __construct(
        private ProductionAccessInterface $access,
        private ProductionLookup $lookup,
        private VersionSnapshot $snapshot,
        private ArchiveRepositoryInterface $archives,
        private VersionView $view,
        private ProductionStatePolicy $states,
        private ClockInterface $clock,
    ) {}

    public function execute(int $actorId, string $groupId, string $idempotencyKey): VersionOutputDto
    {
        $scope = $this->access->scope($actorId);
        if (!$scope->canManage) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $keyHash = hash('sha256', $idempotencyKey);
        $request = hash('sha256', 'version|' . $groupId);
        $now = $this->clock->now();
        $previous = $this->archives->idempotency($actorId, $keyHash);
        if (null !== $previous) {
            $version = $previous['request'] === $request ? $this->archives->find($previous['versionId']) : null;
            if (null === $version) {
                throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
            }

            return $this->view->one($version, $now);
        }
        $group = $this->lookup->group($scope, $groupId);
        $deadline = $this->lookup->deadline($group);
        if (null === $deadline || !$this->states->closed($deadline, $now)) {
            throw new HttpException('PRINT_SALES_OPEN', 409);
        }
        $version = $this->snapshot->take($group, $deadline, $actorId, $now, false);
        if (null === $version) {
            throw new HttpException('VERSION_CONFLICT', 409);
        }
        $this->archives->remember($actorId, $keyHash, $request, $version->publicId);

        return $this->view->one($version, $now);
    }
}
