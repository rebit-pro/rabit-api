<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Shoot\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class ShootListItemResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $id,
        public string $institutionId,
        public string $institutionName,
        public string $name,
        public ?string $date,
        public int $revision,
        public int $groupCount,
        public int $openGroupCount,
    ) {}
}
