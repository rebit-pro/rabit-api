<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\UseCase;

use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Institution\Dto\InstitutionDownloadOutputDto;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\File\Dto\ProtectedFileOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Выдаёт браузеру краткую подписанную ссылку на ZIP без переноса большого файла через память JavaScript.
 * При получении байтов повторно проверяет текущую роль и учреждение сотрудника, срок, поколение и наличие файла.
 */
final readonly class DownloadInstitutionArchiveUseCase
{
    public function __construct(
        private InstitutionArchiveAccess $access,
        private ArchiveLinkInterface $links,
        private ArchiveStorageInterface $storage,
        private ClockInterface $clock,
    ) {}

    public function link(int $actorId, string $archiveId): InstitutionDownloadOutputDto
    {
        $archive = $this->access->archive($actorId, $archiveId);
        $this->ready($archive);
        $expires = min($this->clock->now()->getTimestamp() + 120, $archive->expiresAt?->getTimestamp() ?? 0);
        $payload = $archiveId . '.' . $actorId . '.' . $expires . '.' . $archive->generation;
        $token = $this->links->token('institution-download|' . $payload, $archive->generation);

        return new InstitutionDownloadOutputDto($payload . '.' . $token, new \DateTimeImmutable('@' . $expires));
    }

    public function open(string $ticket): ProtectedFileOutputDto
    {
        if (1 !== preg_match('/^([a-f0-9-]{36})\.([1-9][0-9]{0,9})\.([1-9][0-9]{0,9})\.([1-9][0-9]{0,9})\.([A-Za-z0-9_-]{43})$/D', $ticket, $parts)) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }
        [, $id, $actor, $expires, $generation, $mac] = $parts;
        $payload = $id . '.' . $actor . '.' . $expires . '.' . $generation;
        if ((int)$expires <= $this->clock->now()->getTimestamp() || !hash_equals($this->links->token('institution-download|' . $payload, (int)$generation), $mac)) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }
        try {
            $archive = $this->access->archive((int)$actor, $id);
        } catch (HttpException $error) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404, $error);
        }
        if ($archive->generation !== (int)$generation) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }
        $this->ready($archive);

        return new ProtectedFileOutputDto(
            internalUri: $this->storage->uri((string)$archive->archivePath),
            filename: 'institution-print-' . substr($archive->institutionPublicId, 0, 8) . '-v' . $archive->number . '.zip',
            mimeType: 'application/zip',
            bytes: (int)$archive->bytes,
        );
    }

    private function ready(InstitutionArchive $archive): void
    {
        if (VersionStatusEnum::READY !== $archive->status || null === $archive->expiresAt || $archive->expiresAt <= $this->clock->now()
            || null === $archive->archivePath || null === $archive->bytes || !$this->storage->exists($archive->archivePath)) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }
    }
}
