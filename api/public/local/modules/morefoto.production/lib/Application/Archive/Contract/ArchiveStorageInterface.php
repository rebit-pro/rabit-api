<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Contract;

interface ArchiveStorageInterface
{
    /** Относительный путь архива версии внутри приватного хранилища файлов. */
    public function archivePath(string $groupPublicId, string $versionPublicId): string;

    public function absolutePath(string $relativePath): string;

    /** Адрес internal-location веб-сервера для отдачи через X-Accel-Redirect. */
    public function uri(string $relativePath): string;

    public function exists(string $relativePath): bool;

    public function delete(string $relativePath): void;
}
