<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class StaffChildOutputDto
{
    /** @param list<string> $photoIds */
    public function __construct(
        public int $nativeId,
        public string $id,
        public string $code,
        public array $photoIds,
    ) {}
}
