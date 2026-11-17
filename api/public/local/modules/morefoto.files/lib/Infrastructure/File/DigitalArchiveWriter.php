<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\File;

use Morefoto\Files\Application\Institution\Contract\DigitalArchiveWriterInterface;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;
use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;
use Rebit\Share\Infrastructure\File\AtomicZipWriter;

/** Проверяет исходники, пишет атомарный ZIP без сжатия и CSV с точными путями и всеми основаниями покупки. */
final readonly class DigitalArchiveWriter implements DigitalArchiveWriterInterface
{
    public function __construct(private AtomicZipWriter $zip = new AtomicZipWriter()) {}

    public function build(array $files, string $manifest, string $target): int
    {
        $entries = [];
        $names = ['manifest.csv' => true];
        foreach ($files as $file) {
            if (!$this->exists($file['path'], $file['bytes']) || !is_readable($file['path'])) {
                throw new DigitalArchiveException('ORIGINAL_MISSING');
            }
            if (isset($names[$file['name']]) || str_starts_with($file['name'], '/') || str_contains($file['name'], '\\')
                || 1 === preg_match('~(?:^|/)\.\.?(/|$)~D', $file['name'])) {
                throw new DigitalArchiveException('COMPOSITION_CHANGED');
            }
            $names[$file['name']] = true;
            $entries[] = ['path' => $file['path'], 'name' => $file['name']];
        }
        try {
            return $this->zip->write($entries, ['manifest.csv' => $manifest], $target);
        } catch (\RuntimeException $error) {
            throw new FilesStorageException('Cannot write institution digital archive.', 0, $error);
        }
    }

    public function manifest(DigitalArchive $archive): string
    {
        $stream = fopen('php://temp', 'w+b');
        if (false === $stream) {
            throw new FilesStorageException('Cannot create digital manifest.');
        }
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $this->row($stream, ['Выгрузка', 'Состав на (UTC)', 'Группа', 'ID группы', 'Фото', 'Исходное имя', 'Файл ZIP', 'Байт', 'Заказ', 'ID заказа', 'Строка', 'Покупка', 'Код ребёнка']);
            foreach ($archive->files as $file) {
                foreach ($file->sources as $source) {
                    $this->row($stream, [
                        $archive->id, $archive->capturedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                        $file->groupName, $file->groupId, $file->photoId, $file->filename, $file->entryName,
                        null === $file->bytes ? '' : (string)$file->bytes, $source['orderNumber'], $source['orderId'],
                        $source['lineId'], $source['kind'], $source['childCode'],
                    ]);
                }
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            if (false === $content) {
                throw new FilesStorageException('Cannot read digital manifest.');
            }

            return $content;
        } finally {
            fclose($stream);
        }
    }

    public function exists(string $path, int $bytes): bool
    {
        clearstatcache(true, $path);

        return 0 < $bytes && is_file($path) && $bytes === filesize($path);
    }

    /** @param resource $stream
     * @param list<string> $values
     */
    private function row(mixed $stream, array $values): void
    {
        foreach ($values as &$value) {
            if (1 === preg_match('/^[\s]*[=+\-@]/u', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        if (false === fputcsv($stream, $values, ';', '"', '', "\r\n")) {
            throw new FilesStorageException('Cannot write digital manifest.');
        }
    }
}
