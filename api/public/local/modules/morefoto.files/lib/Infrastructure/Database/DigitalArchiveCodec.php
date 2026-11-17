<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Database;

use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchiveFile;

/** Сериализация неизменяемого файлового состава; DTO и предметные объекты не знают о формате хранения JSON. */
final readonly class DigitalArchiveCodec
{
    /** @param list<DigitalArchiveFile> $files */
    public function encode(array $files): string
    {
        return json_encode($files, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return list<DigitalArchiveFile> */
    public function decode(string $json): array
    {
        /** @var list<array{
         *     groupId: string, groupName: string, photoId: string, filename: string, entryName: string,
         *     relativePath: null|string, bytes: null|int,
         *     sources: list<array{orderId: string, orderNumber: string, lineId: string, kind: string, childCode: string}>,
         * }> $rows */
        $rows = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        $files = [];
        foreach ($rows as $row) {
            $files[] = new DigitalArchiveFile(
                groupId: $row['groupId'],
                groupName: $row['groupName'],
                photoId: $row['photoId'],
                filename: $row['filename'],
                entryName: $row['entryName'],
                relativePath: $row['relativePath'],
                bytes: $row['bytes'],
                sources: $row['sources'],
            );
        }

        return $files;
    }
}
