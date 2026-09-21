<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class StaffRequestMutationResultDto implements ResultDtoInterface
{
    public function __construct(public string $id, public int $revision, public string $status) {}
}
