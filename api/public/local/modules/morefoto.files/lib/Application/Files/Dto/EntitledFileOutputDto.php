<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Dto;

final readonly class EntitledFileOutputDto
{
    /**
     * @param string $filename     уникальное в заказе имя файла, например `AB003.jpg`
     * @param string $relativePath путь оригинала в приватном хранилище Media
     * @param string $absolutePath путь оригинала для чтения фоновой сборкой
     */
    public function __construct(
        public string $photoId,
        public string $childCode,
        public string $code,
        public string $filename,
        public string $mimeType,
        public int $bytes,
        public string $relativePath,
        public string $absolutePath,
    ) {}
}
