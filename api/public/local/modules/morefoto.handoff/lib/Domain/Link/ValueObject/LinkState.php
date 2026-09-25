<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\ValueObject;

/** A group without stored link state has revision 1 and no confirmed preparation. */
final readonly class LinkState
{
    public function __construct(
        public int $revision = 1,
        public ?string $preparedSignature = null,
    ) {}
}
