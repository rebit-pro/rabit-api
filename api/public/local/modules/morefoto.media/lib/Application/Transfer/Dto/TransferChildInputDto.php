<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Transfer\Dto;

final readonly class TransferChildInputDto
{
    /** @param list<string> $expectedPhotoIds */
    public function __construct(
        public string $shootId,
        public string $fromGroupId,
        public string $toGroupId,
        public string $childCode,
        public string $targetCode,
        public array $expectedPhotoIds,
        public int $revision,
    ) {}
}
