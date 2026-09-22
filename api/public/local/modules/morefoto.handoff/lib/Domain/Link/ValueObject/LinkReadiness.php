<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\ValueObject;

use Morefoto\Handoff\Domain\Link\Enum\LinkProblemEnum;

final readonly class LinkReadiness
{
    /** @param list<LinkProblemEnum> $problems */
    public function __construct(
        public string $signature,
        public array $problems,
    ) {}
}
