<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\File;

use Rebit\Share\Domain\File\Exception\InvalidFileException;

/** Server-side policy for technical public uploads, not private MoreFoto originals. */
final readonly class TechnicalUploadValidator
{
    public const int DEFAULT_MAX_BYTES = 15 * 1024 * 1024;

    /** @var array<string, list<string>> */
    private const array EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'text/plain' => ['txt'],
        'application/zip' => ['zip'],
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/webp' => ['webp'],
    ];

    public function __construct(private int $maxSizeBytes = self::DEFAULT_MAX_BYTES) {}

    /**
     * @param array<string, mixed> $file
     *
     * @return array{name: string, type: string, tmpName: string, size: int}
     */
    public function validate(array $file): array
    {
        foreach (['name', 'tmp_name'] as $field) {
            if (!is_string($file[$field] ?? null) || '' === $file[$field]) {
                throw new InvalidFileException('Ожидается один корректный файл.');
            }
        }
        if (!is_int($file['error'] ?? null) || UPLOAD_ERR_OK !== $file['error']) {
            throw new InvalidFileException('Файл не был полностью загружен.');
        }

        $path = $file['tmp_name'];
        if (!is_uploaded_file($path) || !is_readable($path)) {
            throw new InvalidFileException('Некорректный временный файл.');
        }
        $size = filesize($path);
        if (false === $size || 0 >= $size || $this->maxSizeBytes < $size) {
            throw new InvalidFileException('Файл пустой или превышает допустимый размер.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !isset(self::EXTENSIONS[$mime])) {
            throw new InvalidFileException('Недопустимый тип файла.');
        }

        $name = basename(str_replace('\\', '/', $file['name']));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        if (!is_string($name) || '' === $name || '.' === $name || '..' === $name || 120 < mb_strlen($name)) {
            throw new InvalidFileException('Некорректное имя файла.');
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS[$mime], true)
            || 1 === preg_match('/[<>:"|?*]/u', $name)) {
            throw new InvalidFileException('Расширение файла не соответствует содержимому.');
        }

        return ['name' => $name, 'type' => $mime, 'tmpName' => $path, 'size' => $size];
    }
}
