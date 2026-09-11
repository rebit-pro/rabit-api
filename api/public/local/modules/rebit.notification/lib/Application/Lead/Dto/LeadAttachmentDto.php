<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Lead\Dto;

/**
 * Проверенное вложение к заявке (файл ТЗ).
 *
 * Создаётся только после успешной серверной валидации (см. UploadedFileValidator).
 * Файл не сохраняется на диск приложения — $path указывает на PHP-temp загрузки
 * ($_FILES[...]['tmp_name']), который PHP удаляет сам после завершения запроса.
 */
final readonly class LeadAttachmentDto
{
    public function __construct(
        /** Путь к временному файлу загрузки (tmp_name). */
        public string $path,
        /** Очищенное имя файла для отправки получателю. */
        public string $name,
        /** MIME-тип, определённый по содержимому файла. */
        public string $mimeType,
        /** Размер файла в байтах. */
        public int $size,
    ) {}
}
