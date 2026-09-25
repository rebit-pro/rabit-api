<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class AvatarOutputDto implements ResultDtoInterface
{
    public function __construct(
        public int $version,
        public string $thumbUrl,
        public string $fullUrl,
    ) {}
}
