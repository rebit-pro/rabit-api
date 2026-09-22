<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class LinkPreparationResultDto implements ResultDtoInterface
{
    public function __construct(
        public int $revision,
        public string $signature,
        public bool $prepared,
    ) {}
}
