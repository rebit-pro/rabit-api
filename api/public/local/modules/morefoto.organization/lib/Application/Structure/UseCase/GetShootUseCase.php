<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\UseCase;

use Morefoto\Organization\Application\Structure\Dto\StructurePageInputDto;
use Morefoto\Organization\Application\Structure\Dto\StructurePageOutputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootDetailOutputDto;
use Morefoto\Organization\Application\Structure\Dto\GroupOutputDto;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Morefoto\Organization\Domain\Calendar\Repository\GroupStateSql;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Открывает организатору съёмку: страницу её групп с ответственными и сроками и разбивку всех групп съёмки по
 * состояниям приёма. Если доступ или назначения изменились во время чтения, ответ отклоняется целиком.
 */
final readonly class GetShootUseCase
{
    public function __construct(private StructureRepository $structure, private InstitutionAccessInterface $access, private GroupAccessInterface $groupAccess, private TokenResolverInterface $tokens, private CalendarClockInterface $clock) {}

    public function execute(int $actor, string $bearer, StructureId $shootId, StructurePageInputDto $input): ShootDetailOutputDto
    {
        $scope = $this->access->scope($actor);
        if ('organizer' !== $scope->role) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $signature = $this->groupAccess->signature();
        $shoot = $this->structure->shoot($shootId)->fetch();
        if (false === $shoot) {
            throw new HttpException('NOT_FOUND', 404);
        }
        $now = $this->clock->now();
        $result = $this->structure->groups((int)$shoot['ID'], $input->pageSize, $input->offset(), GroupStateSql::utc($now));
        $rows = [];
        $ids = [];
        $total = 0;
        $byState = GroupStateSql::byState([]);
        while (false !== ($row = $result->fetch())) {
            $total = (int)$row['TOTAL'];
            $byState = GroupStateSql::byState($row);
            if (null !== $row['ID']) {
                $rows[] = $row;
                $ids[] = (int)$row['ID'];
            }
        }
        $assignments = $this->groupAccess->assignments($ids);
        $items = [];
        foreach ($rows as $row) {
            $assignment = $assignments[(int)$row['ID']] ?? new GroupAssignmentOutputDto();
            $items[] = GroupOutputDto::fromRow($row, $shootId->value, (string)$shoot['UF_NAME'], $assignment->teacherId, $now);
        }
        $current = $this->access->scope($actor);
        if ($actor !== $this->tokens->resolveUserId($bearer)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }
        if ($scope->accessRevision !== $current->accessRevision || $scope->role !== $current->role || $signature !== $this->groupAccess->signature()) {
            throw new HttpException('ACCESS_CHANGED', 409);
        }

        return new ShootDetailOutputDto(
            id: $shootId->value,
            institutionId: (string)$shoot['INSTITUTION_PUBLIC_ID'],
            name: (string)$shoot['UF_NAME'],
            date: null === $shoot['UF_DATE'] ? null : (string)$shoot['UF_DATE'],
            revision: (int)$shoot['UF_REVISION'],
            groups: new StructurePageOutputDto($items, ['page' => $input->page, 'pageSize' => $input->pageSize, 'total' => $total, 'totalPages' => (int)ceil($total / $input->pageSize), 'summary' => ['byState' => $byState]]),
            assignmentSignature: $signature,
        );
    }
}
