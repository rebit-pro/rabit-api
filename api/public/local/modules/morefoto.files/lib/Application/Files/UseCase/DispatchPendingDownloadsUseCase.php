<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\UseCase;

use Morefoto\Files\Application\Files\Contract\FilesPublisherInterface;
use Morefoto\Files\Application\Files\Dto\MaintenanceReportOutputDto;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** Восстанавливает сборку архива после потерянного сообщения, паузы повтора или падения обработчика с истёкшей арендой.
 * Сборка, исчерпавшая попытки, завершается ошибкой и освобождает заказ для нового запроса без повторной оплаты.
 */
final readonly class DispatchPendingDownloadsUseCase
{
    public function __construct(
        private DownloadRepositoryInterface $downloads,
        private FilesPublisherInterface $publisher,
        private FileAccessPolicy $policy,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $limit): MaintenanceReportOutputDto
    {
        $now = $this->clock->now();
        $published = 0;
        $failed = 0;
        foreach ($this->downloads->due($now, $limit) as $download) {
            if (FileAccessPolicy::MAX_ATTEMPTS <= $download->attempts) {
                $this->downloads->markFailed($download->publicId, 'ARCHIVE_FAILED', $this->policy->failedRetainedUntil($now));
                ++$failed;
                continue;
            }
            try {
                $this->publisher->build($download->publicId, $download->attempts);
                ++$published;
            } catch (\Throwable $error) {
                $this->logger->warning('Archive build was not requeued.', ['downloadId' => $download->publicId, 'exception' => $error::class]);
                ++$failed;
            }
        }

        return new MaintenanceReportOutputDto($published, $failed);
    }
}
