<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Dto\VersionOutputDto;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Archive\Service\VersionView;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Организатор перевыпускает ссылку версии: прежняя перестаёт открываться (L1-DEC-03). Для архива, удалённого по
 * сроку хранения или не собранного, то же действие ставит сборку заново по сохранённому составу (L1-DEC-04).
 */
final readonly class RenewProductionVersionUseCase
{
    public function __construct(
        private ProductionAccessInterface $access,
        private ProductionLookup $lookup,
        private ArchiveRepositoryInterface $archives,
        private ArchiveLinkInterface $links,
        private VersionView $view,
        private ClockInterface $clock,
    ) {}

    public function execute(int $actorId, string $versionId, string $idempotencyKey): VersionOutputDto
    {
        $scope = $this->access->scope($actorId);
        if (!$scope->canManage) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $keyHash = hash('sha256', $idempotencyKey);
        $request = hash('sha256', 'renewal|' . $versionId);
        $now = $this->clock->now();
        $previous = $this->archives->idempotency($actorId, $keyHash);
        if (null !== $previous) {
            if ($previous['request'] !== $request) {
                throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
            }

            return $this->view->one($this->lookup->version($scope, $versionId), $now);
        }
        $version = $this->lookup->version($scope, $versionId);
        $generation = $version->linkGeneration + 1;
        $renewed = match ($version->status) {
            VersionStatusEnum::READY => $this->archives->renew($version->publicId, $version->linkGeneration, $this->links->hash($this->links->token($version->publicId, $generation))),
            VersionStatusEnum::EXPIRED, VersionStatusEnum::FAILED => $this->archives->renew($version->publicId, $version->linkGeneration, null),
            VersionStatusEnum::PENDING, VersionStatusEnum::EMPTY => throw new HttpException('VERSION_NOT_RENEWABLE', 409),
        };
        if (!$renewed) {
            throw new HttpException('VERSION_CONFLICT', 409);
        }
        $this->archives->remember($actorId, $keyHash, $request, $version->publicId);

        return $this->view->one($this->lookup->version($scope, $versionId), $now);
    }
}
