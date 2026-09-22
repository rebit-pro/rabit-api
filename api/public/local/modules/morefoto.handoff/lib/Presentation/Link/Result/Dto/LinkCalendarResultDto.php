<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class LinkCalendarResultDto implements ResultDtoInterface
{
    public function __construct(
        public int $revision,
        public string $sentAt,
        public string $closesAt,
        public string $deliveryAt,
    ) {}
}
