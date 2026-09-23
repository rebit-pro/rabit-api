<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\UseCase;

use Morefoto\Organization\Application\Institution\Dto\ListInstitutionsInputDto;
use Morefoto\Organization\Application\Institution\Dto\VisibleInstitutionOutputDto;
use Morefoto\Organization\Application\Institution\Dto\VisibleInstitutionPageOutputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Access\Dto\InstitutionAssignmentOutputDto;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Domain\Calendar\Repository\GroupStateSql;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Показывает сотруднику страницу учреждений его области с назначениями и масштабом каждого: сколько съёмок, групп
 * и групп с открытым приёмом. Если доступ изменился во время чтения, ответ отклоняется целиком.
 */
final readonly class ListVisibleInstitutionsUseCase
{
    public function __construct(
        private InstitutionRepository $institutions,
        private InstitutionAccessInterface $access,
        private TokenResolverInterface $tokens,
        private CalendarClockInterface $clock,
    ) {}

    public function execute(int $actor, string $bearer, ListInstitutionsInputDto $input): VisibleInstitutionPageOutputDto
    {
        $scope = $this->access->scope($actor);
        $signature = $this->access->signature();
        $result = $this->institutions->page($input->query, $input->pageSize, $input->offset(), 'organizer' === $scope->role ? null : $scope->institutionIds, GroupStateSql::utc($this->clock->now()));
        $rows = [];
        $ids = [];
        $total = 0;
        while (false !== ($row = $result->fetch())) {
            $total = (int)$row['TOTAL'];
            if (null !== $row['ID']) {
                $rows[] = $row;
                $ids[] = (int)$row['ID'];
            }
        }
        $slots = $this->access->assignments($ids);
        $items = [];
        foreach ($rows as $row) {
            $slot = $slots[(int)$row['ID']] ?? new InstitutionAssignmentOutputDto();
            $items[] = new VisibleInstitutionOutputDto(
                id: (string)$row['UF_PUBLIC_ID'],
                name: (string)$row['UF_NAME'],
                address: (string)$row['UF_ADDRESS'],
                revision: (int)$row['UF_REVISION'],
                curatorId: $slot->curatorId,
                headId: $slot->headId,
                shootCount: (int)$row['SHOOT_COUNT'],
                groupCount: (int)$row['GROUP_COUNT'],
                openGroupCount: (int)$row['OPEN_GROUP_COUNT'],
            );
        }
        $current = $this->access->scope($actor);
        if ($actor !== $this->tokens->resolveUserId($bearer)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }
        if ($scope->accessRevision !== $current->accessRevision || $scope->role !== $current->role || $signature !== $this->access->signature()) {
            throw new HttpException('ACCESS_CHANGED', 409);
        }

        return new VisibleInstitutionPageOutputDto($items, $signature, $input->page, $input->pageSize, $total);
    }
}
