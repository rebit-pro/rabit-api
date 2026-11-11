<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Institution\ValueObject;

final readonly class InstitutionArchiveRequest
{
    public function __construct(public int $actorId, public string $keyHash, public string $requestHash) {}
}
