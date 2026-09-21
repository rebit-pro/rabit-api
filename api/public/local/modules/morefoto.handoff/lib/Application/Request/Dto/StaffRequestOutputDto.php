<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

/**
 * @phpstan-type StaffRequestView array{
 *     id: string, institutionId: string, shootId: string,
 *     createdBy: int, createdByName: string, createdAt: string,
 *     revision: int, status: string,
 *     rows: list<array{id: string, groupId: string, code: string, childCode: string, photoIds: list<string>}>,
 *     comment: string,
 *     history: list<array{kind: string, actorId: int, actorName: string, at: string, comment: string, confirmed: bool}>,
 *     staffEligibility: array{eligible: bool, source: string, verifiedAt: string}
 * }
 */
final readonly class StaffRequestOutputDto
{
    /**
     * @param list<array{id: string, groupId: string, code: string, childCode: string, photoIds: list<string>}>        $rows
     * @param list<array{kind: string, actorId: int, actorName: string, at: string, comment: string, confirmed: bool}> $history
     * @param array{eligible: bool, source: string, verifiedAt: string}                                                $staffEligibility
     */
    public function __construct(
        public string $id,
        public string $institutionId,
        public string $shootId,
        public int $createdBy,
        public string $createdByName,
        public string $createdAt,
        public int $revision,
        public string $status,
        public array $rows,
        public string $comment,
        public array $history,
        public array $staffEligibility,
    ) {}
}
