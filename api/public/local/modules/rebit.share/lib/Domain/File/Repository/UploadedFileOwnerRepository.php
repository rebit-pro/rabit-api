<?php

declare(strict_types=1);

namespace Rebit\Share\Domain\File\Repository;

use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\Type\DateTime;
use Rebit\Share\Domain\File\Entity\Table\UploadedFileOwnerTable;
use Rebit\Share\Domain\File\Exception\FileUploadFailedException;

final readonly class UploadedFileOwnerRepository
{
    /**
     * The cursor contains at most one row: USER_ID: int|string, MODULE_ID: string.
     * Its immediate consumer maps the row; Result never enters the cache or API.
     */
    public function findByFileId(int $fileId): Result
    {
        try {
            return UploadedFileOwnerTable::query()
                ->setSelect(['USER_ID', 'MODULE_ID'])
                ->where('FILE_ID', $fileId)
                ->setLimit(1)
                ->exec()
            ;
        } catch (\Throwable $exception) {
            throw new FileUploadFailedException('Не удалось проверить владельца файла.', previous: $exception);
        }
    }

    /** Ownership is immutable. A duplicate FILE_ID must not replace its owner. */
    public function add(int $fileId, int $userId, string $moduleId): void
    {
        try {
            $result = UploadedFileOwnerTable::add([
                'FILE_ID' => $fileId,
                'USER_ID' => $userId,
                'MODULE_ID' => $moduleId,
                'CREATED_AT' => new DateTime(),
            ]);
        } catch (\Throwable $exception) {
            throw new FileUploadFailedException('Не удалось сохранить владельца файла.', previous: $exception);
        }

        if (!$result->isSuccess()) {
            throw new FileUploadFailedException('Не удалось сохранить владельца файла.');
        }
    }
}
