<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Institution\Result\Dto;

use Morefoto\Organization\Application\Structure\Dto\StructurePageOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;
use Rebit\Share\Infrastructure\Controller\Attribute\SkipWhenNull;

final readonly class InstitutionDetailResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public int $revision,
        public ?int $curatorId,
        public ?int $headId,
        public ?string $curatorName,
        public ?string $headName,
        public StructurePageOutputDto $shoots,
        public StructurePageOutputDto $groups,
        public InstitutionSummaryResultDto $summary,
        #[SkipWhenNull]
        public ?string $assignmentSignature,
    ) {}
}
