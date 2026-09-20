<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class CoverMutationOutputDto implements ResponseDtoInterface
{
    public function __construct(
        public string $photoId,
        public int $revision,
    ) {}
}
