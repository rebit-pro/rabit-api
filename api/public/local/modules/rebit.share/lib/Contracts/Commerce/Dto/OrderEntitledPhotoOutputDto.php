<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class OrderEntitledPhotoOutputDto
{
    /** Кадр строки digital со снимком кодов на момент покупки. */
    public function __construct(
        public string $photoId,
        public string $childCode,
        public string $code,
    ) {}
}
