<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Mapper;

use Morefoto\Handoff\Application\Request\Dto\StaffRequestOutputDto;

/** @phpstan-import-type StaffRequestView from StaffRequestOutputDto */
final readonly class StaffRequestOutputMapper
{
    /** @param StaffRequestView $view */
    public static function fromView(array $view): StaffRequestOutputDto
    {
        return new StaffRequestOutputDto(
            id: $view['id'],
            institutionId: $view['institutionId'],
            shootId: $view['shootId'],
            createdBy: $view['createdBy'],
            createdByName: $view['createdByName'],
            createdAt: $view['createdAt'],
            revision: $view['revision'],
            status: $view['status'],
            rows: $view['rows'],
            comment: $view['comment'],
            history: $view['history'],
            results: $view['results'],
            staffEligibility: $view['staffEligibility'],
        );
    }
}
