<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\UseCase;

use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Domain\Calendar\Repository\GroupStateSql;
use Morefoto\Organization\Application\Institution\Dto\InstitutionDetailInputDto;
use Morefoto\Organization\Application\Institution\Dto\InstitutionDetailOutputDto;
use Morefoto\Organization\Application\Structure\Dto\GroupOutputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootOutputDto;
use Morefoto\Organization\Application\Structure\Dto\StructurePageOutputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Contracts\Access\Dto\InstitutionAssignmentOutputDto;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Открывает учреждение в пределах области сотрудника: страницы съёмок и групп, назначения и разбивку всех видимых
 * групп по состояниям приёма. Если доступ или назначения изменились во время чтения, ответ отклоняется целиком.
 */
final readonly class GetInstitutionDetailUseCase
{
    public function __construct(
        private InstitutionRepository $institutions,
        private StructureRepository $structure,
        private InstitutionAccessInterface $access,
        private GroupAccessInterface $groupAccess,
        private TokenResolverInterface $tokens,
        private CalendarClockInterface $clock,
    ) {}

    public function execute(int $actor, string $bearer, InstitutionId $id, InstitutionDetailInputDto $input): InstitutionDetailOutputDto
    {
        $scope = $this->access->scope($actor);
        if (!in_array($scope->role, ['organizer', 'curator', 'head'], true)) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $signature = $this->access->signature();
        $groupSignature = $this->groupAccess->signature();
        $ids = 'organizer' === $scope->role ? null : $scope->institutionIds;
        /** @var array{
         *     ID: int|string, UF_PUBLIC_ID: string, UF_NAME: string, UF_ADDRESS: string, UF_REVISION: int|string,
         * }|false $institution */
        $institution = $this->institutions->visible($id, $ids)->fetch();
        if (false === $institution) {
            throw new HttpException('NOT_FOUND', 404);
        }
        $nativeId = (int)$institution['ID'];
        $assignments = $this->access->assignments([$nativeId]);
        $assignment = $assignments[$nativeId] ?? new InstitutionAssignmentOutputDto();
        $result = $this->structure->shoots($nativeId, $input->shoots->pageSize, $input->shoots->offset(), $ids);
        /** @var list<ShootOutputDto> $shoots */
        $shoots = [];
        $shootTotal = 0;
        while (false !== ($row = $result->fetch())) {
            /** @var array{
             *     ID: null|int|string, UF_PUBLIC_ID: null|string, UF_NAME: null|string, UF_DATE: null|string,
             *     UF_REVISION: null|int|string, GROUP_COUNT: null|int|string, TOTAL: int|string,
             * } $row */
            $shootTotal = (int)$row['TOTAL'];
            if (null !== $row['ID']) {
                $shoots[] = new ShootOutputDto((string)$row['UF_PUBLIC_ID'], $id->value, (string)$row['UF_NAME'], null === $row['UF_DATE'] ? null : (string)$row['UF_DATE'], (int)$row['UF_REVISION'], (int)$row['GROUP_COUNT']);
            }
        }
        $now = $this->clock->now();
        $result = $this->structure->institutionGroups($nativeId, $input->groups->pageSize, $input->groups->offset(), $ids, GroupStateSql::utc($now));
        /** @var list<array{
         *     ID: int|string, UF_PUBLIC_ID: string, SHOOT_PUBLIC_ID: string, SHOOT_NAME: string, UF_NAME: string, UF_KIND: string,
         *     UF_REVISION: int|string, UF_TIMEZONE: string, UF_SENT_AT: null|string,
         *     UF_CLOSES_AT: null|string, UF_DELIVERY_DUE_AT: null|string, TOTAL: int|string,
         * }> $groupRows */
        $groupRows = [];
        /** @var list<int> $groupIds */
        $groupIds = [];
        $groupTotal = 0;
        $byState = GroupStateSql::byState([]);
        while (false !== ($row = $result->fetch())) {
            $groupTotal = (int)$row['TOTAL'];
            $byState = GroupStateSql::byState($row);
            if (null !== $row['ID']) {
                $groupRows[] = $row;
                $groupIds[] = (int)$row['ID'];
            }
        }
        $teachers = $this->groupAccess->assignments($groupIds);
        /** @var list<GroupOutputDto> $groups */
        $groups = [];
        foreach ($groupRows as $row) {
            $teacher = $teachers[(int)$row['ID']] ?? new GroupAssignmentOutputDto();
            $groups[] = GroupOutputDto::fromRow($row, (string)$row['SHOOT_PUBLIC_ID'], (string)$row['SHOOT_NAME'], $teacher->teacherId, $now);
        }
        $current = $this->access->scope($actor);
        if ($actor !== $this->tokens->resolveUserId($bearer)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }
        if ($scope->accessRevision !== $current->accessRevision || $scope->role !== $current->role || $signature !== $this->access->signature() || $groupSignature !== $this->groupAccess->signature()) {
            throw new HttpException('ACCESS_CHANGED', 409);
        }

        return new InstitutionDetailOutputDto(
            id: $id->value,
            name: (string)$institution['UF_NAME'],
            address: (string)$institution['UF_ADDRESS'],
            revision: (int)$institution['UF_REVISION'],
            curatorId: $assignment->curatorId,
            headId: $assignment->headId,
            curatorName: $assignment->curatorName,
            headName: $assignment->headName,
            shoots: new StructurePageOutputDto($shoots, ['page' => $input->shoots->page, 'pageSize' => $input->shoots->pageSize, 'total' => $shootTotal, 'totalPages' => (int)ceil($shootTotal / $input->shoots->pageSize)]),
            groups: new StructurePageOutputDto($groups, ['page' => $input->groups->page, 'pageSize' => $input->groups->pageSize, 'total' => $groupTotal, 'totalPages' => (int)ceil($groupTotal / $input->groups->pageSize), 'summary' => ['byState' => $byState]]),
            assignmentSignature: 'organizer' === $scope->role ? $signature : null,
        );
    }
}
