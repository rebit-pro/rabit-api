<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Dto\ProductionRunOutputDto;
use Morefoto\Production\Application\Archive\Service\ArchiveBuilder;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Archive\Service\VersionSnapshot;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Organization\PrintClosedGroupsInterface;

/**
 * Регулярный прогон архива печати без участия сотрудника: через час после закрытия печати фиксирует версию 1 групп,
 * закрывшихся после выкладки L1, собирает ожидающие версии и удаляет файлы, срок хранения которых прошёл.
 * Курсор двигается только после записанного снимка, поэтому сбой повторяется со следующим прогоном.
 */
final readonly class RunProductionUseCase
{
    public function __construct(
        private ArchiveRepositoryInterface $archives,
        private PrintClosedGroupsInterface $closedGroups,
        private ProductionLookup $lookup,
        private VersionSnapshot $snapshot,
        private ArchiveBuilder $builder,
        private ArchiveStorageInterface $storage,
        private ArchivePolicy $policy,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $limit): ProductionRunOutputDto
    {
        $now = $this->clock->now();
        $scheduled = $this->schedule($now, $limit);
        $built = 0;
        $failed = 0;
        foreach ($this->archives->due($now, $limit) as $version) {
            $this->builder->build($version) ? ++$built : ++$failed;
        }
        $expired = 0;
        foreach ($this->archives->expired($now, $limit) as $version) {
            $this->storage->delete($version->archivePath);
            $this->archives->markExpired($version->publicId);
            ++$expired;
        }

        return new ProductionRunOutputDto($scheduled, $built, $failed, $expired);
    }

    private function schedule(\DateTimeImmutable $now, int $limit): int
    {
        $cursor = $this->archives->cursor();
        $scheduled = 0;
        foreach ($this->closedGroups->closedBetween($cursor->cursorAt, $cursor->cursorGroupId, $this->policy->cutoff($now), $limit) as $group) {
            $deadline = $this->lookup->deadline($group);
            if (null !== $deadline && null !== $this->snapshot->take($group, $deadline, null, $now, true)) {
                ++$scheduled;
            }
            if (null !== $deadline) {
                $this->archives->advance($deadline, $group->nativeId);
            }
        }
        if (0 < $scheduled) {
            $this->logger->info('Print archives scheduled.', ['versions' => $scheduled]);
        }

        return $scheduled;
    }
}
