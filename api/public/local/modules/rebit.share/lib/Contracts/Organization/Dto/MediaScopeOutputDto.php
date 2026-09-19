<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class MediaScopeOutputDto
{
    public function __construct(
        public int $institutionId,
        public int $shootId,
        public string $shootPublicId,
        public ?int $groupId,
        public ?string $groupPublicId,
    ) {}
}
