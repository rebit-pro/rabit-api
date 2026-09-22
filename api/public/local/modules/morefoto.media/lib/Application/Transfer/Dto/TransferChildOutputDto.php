<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Transfer\Dto;

final readonly class TransferChildOutputDto
{
    /** @param list<string> $photoIds */
    public function __construct(
        public array $photoIds,
        public string $fromGroupId,
        public string $toGroupId,
        public string $childCode,
        public int $revision,
    ) {}
}
