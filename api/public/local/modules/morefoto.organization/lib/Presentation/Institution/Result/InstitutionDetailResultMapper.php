<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Institution\Result;

use Morefoto\Organization\Application\Institution\Dto\InstitutionDetailOutputDto;
use Morefoto\Organization\Presentation\Institution\Result\Dto\InstitutionDetailResultDto;
use Morefoto\Organization\Presentation\Institution\Result\Dto\InstitutionSummaryResultDto;

final readonly class InstitutionDetailResultMapper
{
    public function detail(InstitutionDetailOutputDto $output): InstitutionDetailResultDto
    {
        return new InstitutionDetailResultDto(
            id: $output->id,
            name: $output->name,
            address: $output->address,
            revision: $output->revision,
            curatorId: $output->curatorId,
            headId: $output->headId,
            curatorName: $output->curatorName,
            headName: $output->headName,
            shoots: $output->shoots,
            groups: $output->groups,
            // A6/C4: no financial provider exists yet. Its absence is explicit, never fabricated financial zeros.
            summary: new InstitutionSummaryResultDto(availability: 'unavailable', reason: 'dependenciesNotReady'),
            assignmentSignature: $output->assignmentSignature,
        );
    }
}
