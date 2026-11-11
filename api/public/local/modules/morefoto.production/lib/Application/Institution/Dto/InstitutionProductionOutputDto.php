<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\Dto;

use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;

final readonly class InstitutionProductionOutputDto
{
    /**
     * @param list<GroupDirectoryItemOutputDto> $blockers
     * @param list<InstitutionVersionOutputDto> $versions
     */
    public function __construct(
        public string $institutionId,
        public string $institutionName,
        public string $state,
        public bool $canManage,
        public ?\DateTimeImmutable $buildFrom,
        public int $outsideLines,
        public int $waitingOrders,
        public ?string $error,
        public array $blockers,
        public array $versions,
    ) {}
}
