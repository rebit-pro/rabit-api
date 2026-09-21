<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

final readonly class StaffRequestMutationInputDto
{
    /** @param list<array{id:string,groupId:string,code:string}> $rows */
    public function __construct(
        public string $institutionId,
        public string $shootId,
        public array $rows,
        public string $comment,
        public ?int $revision,
    ) {}
}
