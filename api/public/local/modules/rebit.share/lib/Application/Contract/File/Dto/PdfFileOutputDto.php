<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\File\Dto;

final readonly class PdfFileOutputDto
{
    /** @param string $filename имя файла для сохранения у пользователя, только ASCII-безопасные символы */
    public function __construct(
        public string $filename,
        public string $content,
    ) {}
}
