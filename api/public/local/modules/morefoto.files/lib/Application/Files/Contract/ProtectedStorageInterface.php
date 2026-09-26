<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Contract;

/** Приватное хранилище временных архивов и адреса internal-location для отдачи файлов веб-сервером. */
interface ProtectedStorageInterface
{
    public function archivePath(string $orderPublicId, string $downloadId): string;

    public function absoluteArchivePath(string $relativePath): string;

    public function deleteArchive(string $relativePath): void;

    public function archiveUri(string $relativePath): string;

    public function originalUri(string $mediaRelativePath): string;
}
