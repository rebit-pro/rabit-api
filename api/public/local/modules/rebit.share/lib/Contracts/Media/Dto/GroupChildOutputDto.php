<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class GroupChildOutputDto
{
    /**
     * @param int         $groupId native Organization group ID
     * @param null|string $photoId первый готовый кадр ребёнка в порядке галереи; null — готовых кадров нет
     */
    public function __construct(
        public int $nativeId,
        public string $id,
        public int $groupId,
        public string $code,
        public ?string $photoId,
    ) {}
}
