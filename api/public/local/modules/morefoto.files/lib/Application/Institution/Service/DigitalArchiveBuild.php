<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Service;

use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Institution\Contract\DigitalArchiveWriterInterface;
use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Morefoto\Files\Domain\Institution\Service\DigitalArchivePolicy;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Собирает только сохранённый состав под scoped-блокировкой и владельцем попытки, не переоткрывая покупательских прав. */
final readonly class DigitalArchiveBuild
{
    public function __construct(
        private DigitalArchiveRepositoryInterface $archives,
        private DigitalArchiveAccess $access,
        private OriginalFilesInterface $originals,
        private DigitalArchiveWriterInterface $writer,
        private ProtectedStorageInterface $storage,
        private DigitalArchivePolicy $policy,
        private DownloadIdGeneratorInterface $ids,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function execute(string $id): void
    {
        $this->archives->exclusive('build:' . $id, function() use ($id): void {
            $archive = $this->archives->find($id);
            if (null === $archive || 'pending' !== $archive->status) {
                return;
            }
            $now = $this->clock->now();
            if (DigitalArchivePolicy::MAX_ATTEMPTS <= $archive->attempts) {
                $this->archives->failed($id, $archive->owner, 'ARCHIVE_FAILED', $now);

                return;
            }
            $owner = $this->ids->uuid();
            $path = 'institution-digital/' . $archive->institutionPublicId . '/' . $id . '/' . $owner . '.zip';
            if (!$this->archives->claim($id, $owner, $path, $now, $this->policy->leaseUntil($now))) {
                return;
            }
            try {
                $this->access->institution($archive->actorId, $archive->institutionPublicId, true);
                $files = $this->files($archive);
                $bytes = $this->writer->build($files, $this->writer->manifest($archive), $this->storage->absoluteArchivePath($path));
                $this->access->institution($archive->actorId, $archive->institutionPublicId, true);
                $ready = $this->clock->now();
                if (!$this->archives->ready($id, $owner, $path, $bytes, $ready, $this->policy->expiresAt($ready))) {
                    $this->storage->deleteArchive($path);
                }
            } catch (\Throwable $error) {
                $denied = $error instanceof HttpException && in_array($error->getCode(), [401, 403, 404], true);
                $code = match (true) {
                    $error instanceof DigitalArchiveException => $error->getMessage(),
                    $denied => 'ARCHIVE_ACCESS_REVOKED',
                    default => 'ARCHIVE_FAILED',
                };
                $this->logger->warning('Institution digital archive build failed.', ['archiveId' => $id, 'exception' => $error::class, 'code' => $code]);
                if ($error instanceof DigitalArchiveException || $denied || DigitalArchivePolicy::MAX_ATTEMPTS <= $archive->attempts + 1) {
                    $this->archives->failed($id, $owner, $code, $this->clock->now());
                } else {
                    $this->archives->retry($id, $owner, $this->policy->retryAt($this->clock->now()));
                }
            }
        });
    }

    /** @return list<array{path: string, name: string, bytes: int}> */
    private function files(DigitalArchive $archive): array
    {
        if ([] === $archive->files) {
            throw new DigitalArchiveException('COMPOSITION_CHANGED');
        }
        $files = [];
        $total = 0;
        $sources = 0;
        foreach (array_chunk($archive->files, 500) as $chunk) {
            $ids = [];
            foreach ($chunk as $file) {
                $ids[] = $file->photoId;
            }
            $originals = $this->originals->originals($ids);
            foreach ($chunk as $file) {
                $original = $originals[$file->photoId] ?? null;
                if (null === $original || null === $file->relativePath || null === $file->bytes) {
                    throw new DigitalArchiveException('ORIGINAL_MISSING');
                }
                if ($file->relativePath !== $original->relativePath || $file->bytes !== $original->bytes) {
                    throw new DigitalArchiveException('COMPOSITION_CHANGED');
                }
                $files[] = ['path' => $original->absolutePath, 'name' => $file->entryName, 'bytes' => $file->bytes];
                $total += $file->bytes;
                $sources += count($file->sources);
                $this->policy->assertFits(count($files), $total, $sources);
            }
        }

        return $files;
    }
}
