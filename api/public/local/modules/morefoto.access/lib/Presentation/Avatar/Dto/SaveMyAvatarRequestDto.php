<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Avatar\Dto;

use Rebit\Share\Shared\Interface\RequestImageDtoInterface;

final readonly class SaveMyAvatarRequestDto implements RequestImageDtoInterface
{
    public function __construct(
        public string $tmpName,
        public int $bytes,
    ) {}
}
