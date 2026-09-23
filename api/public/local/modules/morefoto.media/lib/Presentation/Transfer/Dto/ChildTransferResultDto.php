<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Transfer\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class ChildTransferResultDto implements ResultDtoInterface
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
