<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\UseCase;

use Morefoto\Files\Application\Files\Contract\DownloadTokenInterface;
use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Download\Enum\DownloadKindEnum;
use Morefoto\Files\Domain\Download\Enum\DownloadStatusEnum;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\File\Dto\ProtectedFileOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/** FIL-04: перед каждой отдачей байтов заново проверяет ключ или короткую подпись, право заказа, срок и состав загрузки.
 * Готовый архив не обходит истечение права или изменение состава; сам файл отдаёт веб-сервер из internal-location.
 */
final readonly class OpenDownloadContentUseCase
{
    public function __construct(
        private OrderFileAccess $access,
        private DownloadRepositoryInterface $downloads,
        private DownloadTokenInterface $tokens,
        private ProtectedStorageInterface $storage,
        private ClockInterface $clock,
    ) {}

    public function execute(string $downloadId, ?string $orderKey, ?string $token): ProtectedFileOutputDto
    {
        $now = $this->clock->now();
        if (null === $orderKey && (null === $token || !$this->tokens->valid($downloadId, $token, $now))) {
            throw new HttpException('INVALID_DOWNLOAD_TOKEN', 403);
        }
        $download = $this->downloads->find($downloadId);
        if (null === $download) {
            throw new HttpException('DOWNLOAD_NOT_FOUND', 404);
        }
        $access = null === $orderKey ? $this->access->byOrderId($download->orderId) : $this->access->byKey($orderKey);
        if ($download->orderId !== $access->orderId) {
            throw new HttpException('DOWNLOAD_NOT_FOUND', 404);
        }
        match (true) {
            FilesStateEnum::EXPIRED === $access->state => throw new HttpException('FILES_EXPIRED', 410),
            FilesStateEnum::AVAILABLE !== $access->state => throw new HttpException('FILES_UNAVAILABLE', 409, null, ['state' => $access->state->value]),
            DownloadStatusEnum::PENDING === $download->status => throw new HttpException('DOWNLOAD_NOT_READY', 409),
            DownloadStatusEnum::FAILED === $download->status => throw new HttpException('DOWNLOAD_FAILED', 409),
            DownloadStatusEnum::EXPIRED === $download->status, null === $download->expiresAt, $now >= $download->expiresAt => throw new HttpException('DOWNLOAD_EXPIRED', 410),
            default => null,
        };
        foreach ($download->photoIds as $photoId) {
            if (!isset($access->files[$photoId])) {
                throw new HttpException('COMPOSITION_CHANGED', 409);
            }
        }
        if (DownloadKindEnum::FILE === $download->kind) {
            $file = $access->files[$download->photoIds[0]];

            return new ProtectedFileOutputDto($this->storage->originalUri($file->relativePath), $download->filename, $file->mimeType, $file->bytes);
        }
        if (null === $download->archivePath || null === $download->bytes) {
            throw new HttpException('DOWNLOAD_NOT_READY', 409);
        }

        return new ProtectedFileOutputDto($this->storage->archiveUri($download->archivePath), $download->filename, 'application/zip', $download->bytes);
    }
}
