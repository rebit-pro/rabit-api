<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class ChildMoveInputDto
{
    public function __construct(
        public int $childId,
        public int $targetGroupId,
        public string $targetCode,
    ) {}
}
