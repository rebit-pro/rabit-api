<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Service;

use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface;
use Morefoto\Production\Application\Archive\Dto\ArchiveFileInputDto;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;

/**
 * Собирает ZIP версии из сохранённого состава под арендой строки: параллельный процесс не строит тот же архив.
 * Архив пишется по пути, сохранённому при снимке, поэтому повтор после сбоя перезаписывает его атомарно. Пропавший
 * оригинал завершает сборку ошибкой без повторов; технический сбой повторяется до трёх попыток.
 */
final readonly class ArchiveBuilder
{
    public function __construct(
        private ArchiveRepositoryInterface $archives,
        private OriginalFilesInterface $originals,
        private ArchiveWriterInterface $writer,
        private ManifestWriterInterface $manifest,
        private ArchiveStorageInterface $storage,
        private ArchiveLinkInterface $links,
        private ArchivePolicy $policy,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return bool true — архив готов; false — сборку взял другой процесс, она отложена или завершилась ошибкой */
    public function build(ArchiveVersion $due): bool
    {
        $now = $this->clock->now();
        if (ArchivePolicy::MAX_ATTEMPTS <= $due->attempts) {
            // The last attempt died with its lease: no retry is left, the organizer may start it again.
            $this->archives->markFailed($due->publicId, 'ARCHIVE_FAILED');

            return false;
        }
        if (!$this->archives->claim($due->publicId, ArchivePolicy::MAX_ATTEMPTS, $now, $this->policy->leaseUntil($now))) {
            return false;
        }
        $version = $this->archives->find($due->publicId) ?? $due;
        $started = hrtime(true);
        try {
            $lines = $this->archives->lines($version->id);
            $originals = $this->originals->originals(array_values(array_unique(array_map(static fn($line): string => $line->photoId, $lines))));
            $files = [];
            $extensions = [];
            foreach ($lines as $line) {
                $original = $originals[$line->photoId] ?? null;
                if (null === $original) {
                    $this->archives->markFailed($version->publicId, 'ORIGINAL_MISSING');
                    $this->logger->warning('Print archive misses an original.', ['versionId' => $version->publicId]);

                    return false;
                }
                $extension = strtolower(pathinfo($original->relativePath, PATHINFO_EXTENSION));
                $extensions[$line->photoId] = '' === $extension ? 'jpg' : $extension;
                $files[$line->entry] = new ArchiveFileInputDto($original->absolutePath, $line->entry . '.' . $extensions[$line->photoId]);
            }
            $bytes = $this->writer->write(array_values($files), $this->manifest->csv($lines, $extensions), $this->storage->absolutePath($version->archivePath));
            $readyAt = $this->clock->now();
            $this->archives->markReady(
                $version->publicId,
                count($files),
                $bytes,
                $this->links->hash($this->links->token($version->publicId, $version->linkGeneration)),
                $readyAt,
                $this->policy->expiresAt($readyAt),
            );
            $this->logger->info('Print archive ready.', [
                'versionId' => $version->publicId,
                'number' => $version->number,
                'attempt' => $version->attempts,
                'files' => count($files),
                'bytes' => $bytes,
                'buildMs' => intdiv(hrtime(true) - $started, 1_000_000),
            ]);

            return true;
        } catch (\Throwable $error) {
            $this->logger->warning('Print archive build failed.', ['versionId' => $version->publicId, 'attempt' => $version->attempts, 'exception' => $error::class]);
            if (ArchivePolicy::MAX_ATTEMPTS <= $version->attempts) {
                $this->archives->markFailed($version->publicId, 'ARCHIVE_FAILED');
            } else {
                $this->archives->markRetry($version->publicId, $this->policy->retryAt($now));
            }

            return false;
        }
    }
}
