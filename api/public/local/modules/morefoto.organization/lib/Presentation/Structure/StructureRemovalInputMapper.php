<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Structure;

use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Morefoto\Organization\Presentation\Structure\Dto\DeleteGroupRequestDto;
use Morefoto\Organization\Presentation\Structure\Dto\DeleteInstitutionRequestDto;
use Morefoto\Organization\Presentation\Structure\Dto\DeleteShootRequestDto;

/** Stateless mapping of the removal routes to the structure ID and the actor's session token. */
final readonly class StructureRemovalInputMapper
{
    public function id(DeleteGroupRequestDto|DeleteInstitutionRequestDto|DeleteShootRequestDto $request): StructureId
    {
        return new StructureId($request->id);
    }

    /** The use case re-resolves the token under the access lock, so a session revoked while waiting is rejected. */
    public function bearer(DeleteGroupRequestDto|DeleteInstitutionRequestDto|DeleteShootRequestDto $request): string
    {
        return substr($request->authorization, 7);
    }
}
