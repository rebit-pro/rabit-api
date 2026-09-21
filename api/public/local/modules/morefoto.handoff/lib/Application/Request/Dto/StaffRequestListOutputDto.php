<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

/**
 * @phpstan-type StaffRequestScope array{
 *     role: string,
 *     institutions: list<array{id: string, name: string}>,
 *     shoots: list<array{id: string, institutionId: string, name: string}>,
 *     groups: list<array{id: string, institutionId: string, shootId: string, shootName: string, name: string, kind: string, state: string}>
 * }
 */
final readonly class StaffRequestListOutputDto
{
    /**
     * @param list<StaffRequestOutputDto> $items
     * @param StaffRequestScope           $scope
     */
    public function __construct(
        public array $items,
        public array $scope,
        public int $page,
        public int $pageSize,
        public int $total,
        public int $totalPages,
    ) {}
}
