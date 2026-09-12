<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class InstitutionMutationOutputDto implements ResponseDtoInterface
{
    public function __construct(
        public string $id,
        public int $revision,
        public string $assignmentSignature,
    ) {}
}
