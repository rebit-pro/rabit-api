<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class AssignmentOptionsOutputDto implements ResponseDtoInterface
{
    /**
     * @param list<array{id:string,name:string,address:string,curatorId:?int,headId:?int}>                                                  $institutions
     * @param list<array{id:string,name:string,shootId:string,shootName:string,institutionId:string,institutionName:string,teacherId:?int}> $groups
     * @param list<StaffOutputDto>                                                                                                          $staff
     */
    public function __construct(
        public array $institutions,
        public array $groups,
        public array $staff,
        public string $assignmentSignature,
    ) {}
}
