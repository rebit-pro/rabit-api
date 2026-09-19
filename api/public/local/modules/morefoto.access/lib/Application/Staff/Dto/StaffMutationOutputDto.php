<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class StaffMutationOutputDto implements ResponseDtoInterface
{
    public function __construct(
        public int $id,
        public int $revision,
        public int $accessRevision,
        public string $assignmentSignature,
        public string $accountStatus,
    ) {}
}
