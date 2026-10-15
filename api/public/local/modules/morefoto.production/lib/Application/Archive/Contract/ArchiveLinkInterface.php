<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Contract;

interface ArchiveLinkInterface
{
    /** Непредсказуемый ключ ссылки версии и поколения; выводится из серверного секрета и нигде не хранится. */
    public function token(string $versionPublicId, int $generation): string;

    /** Хеш ключа для поиска версии; сам ключ в базе и логах не хранится. */
    public function hash(string $token): string;
}
