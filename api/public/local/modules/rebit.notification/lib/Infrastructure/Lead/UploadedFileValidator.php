<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Lead;

use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Share\Shared\Exception\ValidationHttpException;

/**
 * Серверная валидация загруженного файла ТЗ.
 *
 * Принципы безопасности:
 * - файл не сохраняется на диск приложения — работаем только с PHP-temp загрузки;
 * - MIME определяется по содержимому (finfo), а не по присланному типу/расширению;
 * - whitelist «MIME → расширение» (defense in depth);
 * - имя файла очищается и никогда не используется как путь.
 *
 * Возвращает {@see LeadAttachmentDto} либо null, если файл не приложен
 * (поле необязательное). При нарушении правил бросает {@see ValidationHttpException}.
 */
class UploadedFileValidator
{
    /**
     * Whitelist: MIME по содержимому → каноническое расширение.
     *
     * @var array<string, string>
     */
    private const array ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    private const int MAX_NAME_LENGTH = 120;

    public function __construct(
        private readonly int $maxSizeBytes,
    ) {}

    /**
     * @param null|array<string, mixed> $file результат HttpRequest::getFile('file') / $_FILES['file']
     *
     * @throws ValidationHttpException
     */
    public function validate(?array $file): ?LeadAttachmentDto
    {
        // Файл не приложен — это допустимо, поле необязательное.
        if (null === $file || [] === $file) {
            return null;
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (UPLOAD_ERR_NO_FILE === $error) {
            return null;
        }

        if (UPLOAD_ERR_OK !== $error) {
            throw new ValidationHttpException($this->describeUploadError($error));
        }

        // Несколько файлов в одном поле не поддерживаем (на старте — один файл).
        if (is_array($file['name'] ?? null)) {
            throw new ValidationHttpException('Прикрепите один файл.');
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ('' === $tmpPath || !$this->isUploadedFile($tmpPath)) {
            throw new ValidationHttpException('Не удалось обработать файл, попробуйте ещё раз.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            throw new ValidationHttpException('Файл пустой.');
        }

        if ($size > $this->maxSizeBytes) {
            throw new ValidationHttpException(
                sprintf('Файл больше %d МБ — приложите версию поменьше.', intdiv($this->maxSizeBytes, 1024 * 1024)),
            );
        }

        $mimeType = $this->detectMimeType($tmpPath);
        if (!isset(self::ALLOWED_MIME[$mimeType])) {
            throw new ValidationHttpException(
                'Недопустимый тип файла. Разрешены: pdf, doc, docx, xls, xlsx, txt, zip, png, jpg.',
            );
        }

        $cleanName = $this->sanitizeName((string)($file['name'] ?? ''), self::ALLOWED_MIME[$mimeType]);

        return new LeadAttachmentDto($tmpPath, $cleanName, $mimeType, $size);
    }

    /**
     * Определяет MIME по содержимому файла. Выделено в метод ради тестируемости.
     */
    protected function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (false === $finfo) {
            return '';
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) ? $mime : '';
    }

    /**
     * Проверяет, что путь — реальная HTTP-загрузка. Выделено ради тестируемости.
     */
    protected function isUploadedFile(string $path): bool
    {
        return is_uploaded_file($path);
    }

    /**
     * Очищает имя файла: только basename, без управляющих и опасных символов,
     * с ограничением длины. Гарантирует допустимое расширение.
     */
    private function sanitizeName(string $original, string $fallbackExt): string
    {
        // Берём только имя, отбрасывая любые поданные пути.
        $name = basename(str_replace('\\', '/', $original));

        // Убираем управляющие символы и недопустимые для имени символы.
        $name = (string)preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = (string)preg_replace('#[/:*?"<>|]+#u', '_', $name);
        $name = trim($name);

        if ('' === $name || '.' === $name || '..' === $name) {
            return 'tz.' . $fallbackExt;
        }

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, self::MAX_NAME_LENGTH - 20);
            $name = '' !== $ext ? $base . '.' . $ext : $base;
        }

        return $name;
    }

    private function describeUploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой.',
            UPLOAD_ERR_PARTIAL => 'Файл загрузился не полностью, попробуйте ещё раз.',
            default => 'Не удалось загрузить файл, попробуйте ещё раз.',
        };
    }
}
