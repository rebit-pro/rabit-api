<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\File\Dto;

final readonly class ProtectedFileOutputDto
{
    /**
     * @param string $internalUri адрес internal-location веб-сервера, недоступный снаружи
     * @param string $filename    имя файла для сохранения у пользователя, только ASCII-безопасные символы
     */
    public function __construct(
        public string $internalUri,
        public string $filename,
        public string $mimeType,
        public int $bytes,
    ) {}
}
