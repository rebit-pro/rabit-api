<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Institution\Result\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class InstitutionProductionResultDto implements ResponseDtoInterface
{
    /**
     * @param list<array{
     *     id: string,
     *     name: string,
     *     shootName: string,
     *     deadlineAt: ?string,
     * }> $blockers
     * @param list<InstitutionVersionResultDto> $versions
     */
    public function __construct(
        public string $institutionId,
        public string $institutionName,
        public string $state,
        public bool $canManage,
        public ?string $buildFrom,
        public int $outsideLines,
        public int $waitingOrders,
        public ?string $error,
        public array $blockers,
        public array $versions,
    ) {}
}
