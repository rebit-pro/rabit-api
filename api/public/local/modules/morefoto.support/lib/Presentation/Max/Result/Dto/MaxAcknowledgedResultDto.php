<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Max\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class MaxAcknowledgedResultDto implements ResultDtoInterface
{
    public function __construct(public bool $acknowledged) {}
}
