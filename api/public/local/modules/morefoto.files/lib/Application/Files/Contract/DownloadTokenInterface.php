<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Contract;

/** Короткая подпись ссылки на одну загрузку: заменяет личный ключ заказа только для её содержимого и только до срока. */
interface DownloadTokenInterface
{
    public function issue(string $downloadId, \DateTimeImmutable $expiresAt): string;

    public function valid(string $downloadId, string $token, \DateTimeImmutable $now): bool;
}
