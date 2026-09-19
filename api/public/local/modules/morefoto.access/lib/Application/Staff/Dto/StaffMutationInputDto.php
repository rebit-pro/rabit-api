<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

use Morefoto\Access\Domain\Staff\Enum\RoleEnum;

final readonly class StaffMutationInputDto
{
    /**
     * @param list<string> $institutionIds
     * @param list<string> $groupIds
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $email,
        public RoleEnum $role,
        public bool $active,
        public array $institutionIds,
        public array $groupIds,
        public bool $replaceAssignments,
        public ?string $assignmentSignature,
        public ?string $reason,
        public ?int $revision,
    ) {}

    public function payloadHash(): string
    {
        return hash('sha256', json_encode([
            $this->name,
            $this->email,
            $this->role->value,
            $this->active,
            $this->institutionIds,
            $this->groupIds,
            $this->replaceAssignments,
            $this->assignmentSignature,
            $this->reason,
            $this->revision,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
