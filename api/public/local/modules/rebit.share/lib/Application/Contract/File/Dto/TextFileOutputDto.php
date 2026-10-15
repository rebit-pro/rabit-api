<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\File\Dto;

final readonly class TextFileOutputDto
{
    /**
     * @param string $filename имя файла для сохранения у пользователя, только ASCII-безопасные символы
     * @param string $mimeType тип содержимого с кодировкой, например `text/csv; charset=utf-8`
     */
    public function __construct(
        public string $filename,
        public string $mimeType,
        public string $content,
    ) {}
}
