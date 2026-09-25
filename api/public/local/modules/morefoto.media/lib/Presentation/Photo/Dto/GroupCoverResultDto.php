<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class GroupCoverResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $photoId,
        public int $revision,
    ) {}
}
