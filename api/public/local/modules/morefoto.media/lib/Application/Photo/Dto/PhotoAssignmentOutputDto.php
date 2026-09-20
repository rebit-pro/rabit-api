<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class PhotoAssignmentOutputDto implements ResponseDtoInterface
{
    public function __construct(
        public string $childId,
        public string $childCode,
        public int $sequence,
        public string $code,
    ) {}
}
