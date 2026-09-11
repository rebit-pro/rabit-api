<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Lead\Dto\Result;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class SubmitLeadResultDto implements ResultDtoInterface
{
    public function __construct(
        public bool $accepted = true,
    ) {}
}
