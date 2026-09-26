<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class DeletionMutationOutputDto implements ResponseDtoInterface
{
    public function __construct(
        public int $deleted,
        public int $revision,
    ) {}
}
