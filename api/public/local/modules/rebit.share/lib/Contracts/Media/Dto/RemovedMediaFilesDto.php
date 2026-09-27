<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

/** Файлы удалённых кадров; стираются после фиксации транзакции. */
final readonly class RemovedMediaFilesDto
{
    /**
     * @param list<array{
     *     photoId: string,
     *     originalPath: null|string,
     * }> $photos
     */
    public function __construct(public array $photos) {}
}
