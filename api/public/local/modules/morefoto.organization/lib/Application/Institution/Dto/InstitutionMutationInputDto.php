<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

final readonly class InstitutionMutationInputDto
{
    public function __construct(
        public string $key,
        public ?string $name,
        public ?string $address,
        public ?int $revision,
        public bool $curatorProvided,
        public ?int $curatorId,
        public bool $headProvided,
        public ?int $headId,
        public ?string $assignmentSignature,
        public bool $replaceAssignments,
    ) {}

    public function payloadHash(): string
    {
        return hash('sha256', json_encode([$this->name, $this->address, $this->revision, $this->curatorProvided, $this->curatorId, $this->headProvided, $this->headId, $this->assignmentSignature, $this->replaceAssignments], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
