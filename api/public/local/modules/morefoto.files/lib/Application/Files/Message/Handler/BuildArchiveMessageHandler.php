<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Message\Handler;

use Morefoto\Files\Application\Files\Contract\ArchiveBuilderInterface;
use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Files\Message\BuildArchiveMessage;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/**
 * Собирает архив загрузки из текущих разрешённых оригиналов под арендой строки, чтобы дубль сообщения не строил архив дважды.
 * Потеря права или изменение состава завершают сборку без повторов; технический сбой повторяется до трёх попыток.
 */
final readonly class BuildArchiveMessageHandler
{
    public function __construct(
        private DownloadRepositoryInterface $downloads,
        private OrderFileAccess $access,
        private ArchiveBuilderInterface $builder,
        private ProtectedStorageInterface $storage,
        private FileAccessPolicy $policy,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(BuildArchiveMessage $message): void
    {
        $now = $this->clock->now();
        if (!$this->downloads->claim($message->downloadId, FileAccessPolicy::MAX_ATTEMPTS, $now, $this->policy->leaseUntil($now))) {
            return;
        }
        $download = $this->downloads->find($message->downloadId);
        if (null === $download) {
            return;
        }
        $started = hrtime(true);
        try {
            $access = $this->access->byOrderId($download->orderId);
            if (FilesStateEnum::AVAILABLE !== $access->state || null === $access->availableUntil) {
                $this->fail($download->publicId, 'FILES_UNAVAILABLE', $now);

                return;
            }
            $files = [];
            foreach ($download->photoIds as $photoId) {
                if (!isset($access->files[$photoId])) {
                    $this->fail($download->publicId, 'COMPOSITION_CHANGED', $now);

                    return;
                }
                $files[] = $access->files[$photoId];
            }
            if ([] === $files) {
                $this->fail($download->publicId, 'COMPOSITION_CHANGED', $now);

                return;
            }
            $path = $this->storage->archivePath($access->orderPublicId, $download->publicId);
            $bytes = $this->builder->build($files, $this->storage->absoluteArchivePath($path));
            $finished = $this->clock->now();
            $this->downloads->markReady($download->publicId, $path, $bytes, $this->policy->downloadExpiresAt($finished, $access->availableUntil));
            $this->logger->info('Archive ready.', [
                'downloadId' => $download->publicId,
                'attempt' => $download->attempts,
                'files' => count($files),
                'bytes' => $bytes,
                'buildMs' => intdiv(hrtime(true) - $started, 1_000_000),
            ]);
        } catch (\Throwable $error) {
            $this->logger->warning('Archive build failed.', ['downloadId' => $download->publicId, 'attempt' => $download->attempts, 'exception' => $error::class]);
            if (FileAccessPolicy::MAX_ATTEMPTS <= $download->attempts) {
                $this->fail($download->publicId, 'ARCHIVE_FAILED', $now);
            } else {
                $this->downloads->markRetry($download->publicId, $this->policy->retryAt($now));
            }
        }
    }

    /** Неудачная загрузка хранится сутки, чтобы покупатель увидел причину, затем её убирает purge. */
    private function fail(string $downloadId, string $code, \DateTimeImmutable $now): void
    {
        $this->downloads->markFailed($downloadId, $code, $this->policy->failedRetainedUntil($now));
    }
}
