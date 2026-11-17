<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Dto\ProductionRunOutputDto;
use Morefoto\Production\Application\Archive\Service\ArchiveBuilder;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Archive\Service\VersionSnapshot;
use Morefoto\Production\Domain\Archive\Exception\ArchiveLayoutException;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Organization\PrintClosedGroupsInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Регулярный прогон архива печати без участия сотрудника: через час после закрытия печати фиксирует версию 1 групп,
 * закрывшихся после выкладки L1, собирает ожидающие версии и удаляет файлы, срок хранения которых прошёл.
 * Ошибка имени одной группы удерживает постоянный курсор для повтора, а локальный обход продолжает следующие
 * группы; ожидающие сборки и удаление по сроку выполняются независимо от отклонённого снимка.
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
        $schedule = $this->schedule($now, $limit);
        $built = 0;
        $failed = $schedule['failed'];
        foreach ($this->archives->due($now, $limit) as $version) {
            $this->builder->build($version) ? ++$built : ++$failed;
        }
        $expired = 0;
        foreach ($this->archives->expired($now, $limit) as $version) {
            $this->storage->delete($version->archivePath);
            $this->archives->markExpired($version->publicId);
            ++$expired;
        }

        return new ProductionRunOutputDto($schedule['scheduled'], $built, $failed, $expired);
    }

    /** @return array{scheduled: int, failed: int} */
    private function schedule(\DateTimeImmutable $now, int $limit): array
    {
        $cursor = $this->archives->cursor();
        $after = $cursor->cursorAt;
        $afterGroupId = $cursor->cursorGroupId;
        $cutoff = $this->policy->cutoff($now);
        $scheduled = 0;
        $failed = 0;
        $blocked = false;
        while ($scheduled < $limit) {
            $groups = $this->closedGroups->closedBetween($after, $afterGroupId, $cutoff, $limit);
            foreach ($groups as $group) {
                $deadline = $this->lookup->deadline($group);
                if (null === $deadline) {
                    // Без ключа сортировки поставщика нельзя безопасно продолжить страницы или сдвинуть курсор.
                    break 2;
                }
                $after = $deadline;
                $afterGroupId = $group->nativeId;
                try {
                    if (null !== $this->snapshot->take($group, $deadline, null, $now, true)) {
                        ++$scheduled;
                    }
                } catch (HttpException $error) {
                    if (!$error->getPrevious() instanceof ArchiveLayoutException) {
                        throw $error;
                    }
                    $blocked = true;
                    ++$failed;
                    $this->logger->warning('Print archive snapshot rejected.', ['groupId' => $group->id, 'errorCode' => $error->getMessage()]);
                }
                // Не теряем отказавшую группу: успешные снимки за ней при повторном обходе пропускает firstOnly.
                if (!$blocked) {
                    $this->archives->advance($deadline, $group->nativeId);
                }
                if ($scheduled >= $limit) {
                    break;
                }
            }
            if (count($groups) < $limit) {
                break;
            }
        }
        if (0 < $scheduled) {
            $this->logger->info('Print archives scheduled.', ['versions' => $scheduled]);
        }

        return ['scheduled' => $scheduled, 'failed' => $failed];
    }
}
