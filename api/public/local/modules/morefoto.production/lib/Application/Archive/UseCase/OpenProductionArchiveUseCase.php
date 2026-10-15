<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\File\Dto\ProtectedFileOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * PRD-10: лаборатория скачивает готовый ZIP по ссылке без входа. Перед каждой выдачей ключ сверяется с текущим
 * поколением версии, статусом и сроком хранения; неверный, отозванный, истёкший ключ и ключ другой версии отвечают
 * одинаково, не раскрывая причину. Байты отдаёт веб-сервер из internal-location.
 */
final readonly class OpenProductionArchiveUseCase
{
    public function __construct(
        private ArchiveRepositoryInterface $archives,
        private ArchiveLinkInterface $links,
        private ArchiveStorageInterface $storage,
        private ClockInterface $clock,
    ) {}

    public function execute(string $token): ProtectedFileOutputDto
    {
        $version = 1 === preg_match('/^[A-Za-z0-9_-]{43}$/D', $token) ? $this->archives->findByLinkHash($this->links->hash($token)) : null;
        if (null === $version || VersionStatusEnum::READY !== $version->status || null === $version->expiresAt || null === $version->bytes
            || $this->clock->now() >= $version->expiresAt
            || !hash_equals($this->links->token($version->publicId, $version->linkGeneration), $token)
            || !$this->storage->exists($version->archivePath)) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }

        return new ProtectedFileOutputDto(
            internalUri: $this->storage->uri($version->archivePath),
            filename: 'print-' . substr($version->groupPublicId, 0, 8) . '-v' . $version->number . '.zip',
            mimeType: 'application/zip',
            bytes: $version->bytes,
        );
    }
}
