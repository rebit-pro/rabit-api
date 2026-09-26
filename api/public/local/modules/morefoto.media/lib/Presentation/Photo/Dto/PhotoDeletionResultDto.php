<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PhotoDeletionResultDto implements ResultDtoInterface
{
    public function __construct(
        public int $deleted,
        public int $revision,
    ) {}
}
