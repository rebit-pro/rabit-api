<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\UseCase;

use Morefoto\Organization\Application\Institution\Dto\InstitutionOutputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionDetails;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Ramsey\Uuid\Uuid;

/** Internal command. C2 must authorize the caller before exposing it through HTTP. */
final readonly class CreateInstitutionUseCase
{
    public function __construct(private InstitutionRepository $institutions) {}

    public function execute(InstitutionDetails $details): InstitutionOutputDto
    {
        $id = new InstitutionId(Uuid::uuid4()->toString());
        $this->institutions->create($id, $details);

        return new InstitutionOutputDto($id->value, $details->name, $details->address, 1);
    }
}
