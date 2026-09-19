<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class StaffDetailOutputDto implements ResponseDtoInterface
{
    /**
     * @param list<string> $institutionIds
     * @param list<string> $groupIds
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public bool $active,
        public int $revision,
        public int $accessRevision,
        public string $accountStatus,
        public array $institutionIds,
        public array $groupIds,
        public string $assignmentSignature,
    ) {}
}
