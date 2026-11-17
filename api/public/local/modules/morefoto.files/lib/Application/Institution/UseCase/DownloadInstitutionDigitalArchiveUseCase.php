<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\UseCase;

use Morefoto\Files\Application\Files\Contract\DownloadTokenInterface;
use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Institution\Contract\DigitalArchiveWriterInterface;
use Morefoto\Files\Application\Institution\Dto\DigitalArchiveLinkOutputDto;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveAccess;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\File\Dto\ProtectedFileOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Подписывает двухминутный доступ сотрудника; перед отдачей nginx проверяет срок ZIP, текущую роль и назначение. */
final readonly class DownloadInstitutionDigitalArchiveUseCase
{
    public function __construct(
        private DigitalArchiveAccess $access,
        private DownloadTokenInterface $tokens,
        private ProtectedStorageInterface $storage,
        private DigitalArchiveWriterInterface $writer,
        private ClockInterface $clock,
    ) {}

    public function link(int $actorId, string $id): DigitalArchiveLinkOutputDto
    {
        $archive = $this->access->archive($actorId, $id);
        $this->ready($archive);
        $expires = min($this->clock->now()->modify('+2 minutes'), $archive->expiresAt);

        return new DigitalArchiveLinkOutputDto($id . '.' . $actorId . '.' . $this->tokens->issue($this->context($id, $actorId), $expires), $expires);
    }

    public function open(string $ticket): ProtectedFileOutputDto
    {
        if (1 !== preg_match('/^([a-f0-9-]{36})\.([1-9][0-9]{0,9})\.([0-9]{1,12}\.[a-f0-9]{64})$/D', $ticket, $parts)) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }
        [, $id, $actor, $token] = $parts;
        if (!$this->tokens->valid($this->context($id, (int)$actor), $token, $this->clock->now())) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }
        try {
            $archive = $this->access->archive((int)$actor, $id);
        } catch (HttpException $error) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404, $error);
        }
        $this->ready($archive);

        return new ProtectedFileOutputDto(
            internalUri: $this->storage->archiveUri((string)$archive->archivePath),
            filename: 'institution-digital-' . $archive->id . '.zip',
            mimeType: 'application/zip',
            bytes: (int)$archive->bytes,
        );
    }

    private function ready(DigitalArchive $archive): void
    {
        if ('ready' !== $archive->status || null === $archive->expiresAt || $archive->expiresAt <= $this->clock->now()
            || null === $archive->archivePath || null === $archive->bytes
            || !$this->writer->exists($this->storage->absoluteArchivePath($archive->archivePath), $archive->bytes)) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }
    }

    private function context(string $id, int $actor): string
    {
        return 'institution-digital|' . $id . '|' . $actor;
    }
}
