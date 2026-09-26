<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\UseCase;

use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Files\Dto\MaintenanceReportOutputDto;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** Удаляет с диска архивы с наступившим сроком и помечает загрузки истёкшими, чтобы временные архивы не копились в приватном хранилище. */
final readonly class PurgeDownloadsUseCase
{
    public function __construct(
        private DownloadRepositoryInterface $downloads,
        private ProtectedStorageInterface $storage,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $limit): MaintenanceReportOutputDto
    {
        $purged = 0;
        $failed = 0;
        foreach ($this->downloads->stale($this->clock->now(), $limit) as $download) {
            try {
                if (null !== $download->archivePath) {
                    $this->storage->deleteArchive($download->archivePath);
                }
                $this->downloads->markExpired($download->publicId);
                ++$purged;
            } catch (\Throwable $error) {
                $this->logger->warning('Expired archive was not removed.', ['downloadId' => $download->publicId, 'exception' => $error::class]);
                ++$failed;
            }
        }

        return new MaintenanceReportOutputDto($purged, $failed);
    }
}
