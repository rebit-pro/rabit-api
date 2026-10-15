<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class GroupProductionStatusResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $groupId,
        public string $state,
        public ?int $version,
    ) {}
}
