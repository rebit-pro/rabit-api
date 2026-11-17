<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\UseCase;

use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Files\Dto\MaintenanceReportOutputDto;
use Morefoto\Files\Application\Institution\Contract\DigitalArchivePublisherInterface;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** Восстанавливает потерянные сборки и убирает истёкшие ZIP, сохраняя состав; чужой работающий исполнитель не очищается. */
final readonly class MaintainInstitutionDigitalArchivesUseCase
{
    public function __construct(
        private DigitalArchiveRepositoryInterface $archives,
        private DigitalArchivePublisherInterface $publisher,
        private ProtectedStorageInterface $storage,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function dispatch(int $limit): MaintenanceReportOutputDto
    {
        $processed = 0;
        $failed = 0;
        foreach ($this->archives->due($this->clock->now(), $limit) as $archive) {
            try {
                $this->publisher->build($archive['id'], $archive['attempts']);
                ++$processed;
            } catch (\Throwable $error) {
                $this->logger->warning('Digital archive was not dispatched.', ['archiveId' => $archive['id'], 'exception' => $error::class]);
                ++$failed;
            }
        }

        return new MaintenanceReportOutputDto($processed, $failed);
    }

    public function purge(int $limit): MaintenanceReportOutputDto
    {
        $processed = 0;
        $failed = 0;
        foreach ($this->archives->stale($this->clock->now(), $limit) as $candidate) {
            try {
                $removed = $this->archives->exclusive('build:' . $candidate, function() use ($candidate): bool {
                    $archive = $this->archives->find($candidate);
                    $now = $this->clock->now();
                    if (null === $archive || !in_array($archive->status, ['ready', 'failed'], true) || null === $archive->expiresAt || $now < $archive->expiresAt
                        || ([] === $archive->workPaths && null === $archive->archivePath)) {
                        return false;
                    }
                    foreach ($archive->workPaths as $path) {
                        $this->storage->deleteArchive($path);
                    }
                    $this->archives->purged($archive->id, $now);

                    return true;
                });
                if ($removed) {
                    ++$processed;
                }
            } catch (\Throwable $error) {
                $this->logger->warning('Digital archive was not purged.', ['archiveId' => $candidate, 'exception' => $error::class]);
                ++$failed;
            }
        }

        return new MaintenanceReportOutputDto($processed, $failed);
    }
}
