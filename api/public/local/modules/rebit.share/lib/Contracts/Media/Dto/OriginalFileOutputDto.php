<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class OriginalFileOutputDto
{
    /**
     * @param string $relativePath путь внутри приватного хранилища оригиналов, без `..` и ведущего `/`
     * @param string $absolutePath путь файла для чтения фоновой сборкой
     * @param int    $bytes        размер оригинала
     */
    public function __construct(
        public string $photoId,
        public string $mimeType,
        public int $bytes,
        public string $relativePath,
        public string $absolutePath,
    ) {}
}
