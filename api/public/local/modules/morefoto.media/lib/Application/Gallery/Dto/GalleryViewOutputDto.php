<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Dto;

use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;

final readonly class GalleryViewOutputDto
{
    /** @param string $token 64-символьный ключ галереи, к которому сведён запрошенный ключ или псевдоним */
    public function __construct(
        public string $token,
        public GalleryAccessOutputDto $access,
    ) {}
}
