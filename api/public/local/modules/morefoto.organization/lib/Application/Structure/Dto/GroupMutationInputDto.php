<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

use Morefoto\Organization\Domain\Structure\ValueObject\StructureName;

final readonly class GroupMutationInputDto
{
    public ?string $name;
    public ?string $reason;

    public function __construct(public string $key, ?string $name, public ?string $groupKind, public ?int $revision, public bool $teacherProvided, public ?int $teacherId, public ?string $assignmentSignature, public bool $replaceAssignments, ?string $reason = null)
    {
        $this->name = null === $name ? null : (new StructureName($name))->value;
        $this->reason = null === $reason ? null : trim($reason);
        if (1 !== preg_match('/^[a-f0-9]{32}$/D', $key)
            || (null !== $revision && (1 > $revision || 2147483646 < $revision))
            || (null !== $groupKind && !in_array($groupKind, ['regular', 'staff'], true))
            || (null !== $teacherId && (1 > $teacherId || 2147483647 < $teacherId))
            || (null !== $assignmentSignature && 1 !== preg_match('/^a[1-9][0-9]{0,18}$/D', $assignmentSignature))
            || (null !== $reason && (!mb_check_encoding($reason, 'UTF-8') || 1 === preg_match('/[\x00-\x1F\x7F]/', $reason) || '' === $this->reason || 2000 < mb_strlen($this->reason)))) {
            throw new \InvalidArgumentException('Invalid group operation.');
        }
    }

    public function payloadHash(): string
    {
        return hash('sha256', json_encode([$this->name, $this->groupKind, $this->revision, $this->teacherProvided, $this->teacherId, $this->assignmentSignature, $this->replaceAssignments, $this->reason], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
