<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Feedback\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class FeedbackAcceptedResultDto implements ResultDtoInterface
{
    public function __construct(
        public int $number,
    ) {}
}
