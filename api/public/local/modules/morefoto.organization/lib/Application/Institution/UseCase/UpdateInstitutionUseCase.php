<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\UseCase;

use Morefoto\Organization\Application\Institution\Dto\InstitutionOutputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionDetails;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;

final readonly class UpdateInstitutionUseCase
{
    public function __construct(private InstitutionRepository $institutions) {}

    public function execute(InstitutionId $id, InstitutionDetails $details, int $expectedRevision): InstitutionOutputDto
    {
        $revision = $this->institutions->update($id, $details, $expectedRevision);

        return new InstitutionOutputDto($id->value, $details->name, $details->address, $revision);
    }
}
