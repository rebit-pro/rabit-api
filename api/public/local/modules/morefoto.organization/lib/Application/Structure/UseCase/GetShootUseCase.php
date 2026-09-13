<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\UseCase;

use Morefoto\Organization\Application\Structure\Dto\StructurePageInputDto;
use Morefoto\Organization\Application\Structure\Dto\StructurePageOutputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootDetailOutputDto;
use Morefoto\Organization\Application\Structure\Dto\GroupOutputDto;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Calendar\Service\CalendarProjection;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Shared\Exception\HttpException;

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
        $result = $this->structure->groups((int)$shoot['ID'], $input->pageSize, $input->offset());
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
        $assignments = $this->groupAccess->assignments($ids);
        $now = $this->clock->now();
        $items = [];
        foreach ($rows as $row) {
            $calendar = CalendarProjection::create(GroupCalendar::fromStorage(
                null === $row['UF_SENT_AT'] ? null : (string)$row['UF_SENT_AT'],
                null === $row['UF_CLOSES_AT'] ? null : (string)$row['UF_CLOSES_AT'],
                null === $row['UF_DELIVERY_DUE_AT'] ? null : (string)$row['UF_DELIVERY_DUE_AT'],
                (string)$row['UF_TIMEZONE'],
            ), $now);
            $assignment = $assignments[(int)$row['ID']] ?? new GroupAssignmentOutputDto();
            $items[] = new GroupOutputDto(
                id: (string)$row['UF_PUBLIC_ID'],
                shootId: $shootId->value,
                name: (string)$row['UF_NAME'],
                groupKind: (string)$row['UF_KIND'],
                revision: (int)$row['UF_REVISION'],
                teacherId: $assignment->teacherId,
                status: $calendar->status,
                timezone: $calendar->timezone,
                sentAt: $calendar->sentAt,
                closesAt: $calendar->closesAt,
                deliveryDueAt: $calendar->deliveryDueAt,
            );
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
            groups: new StructurePageOutputDto($items, ['page' => $input->page, 'pageSize' => $input->pageSize, 'total' => $total, 'totalPages' => (int)ceil($total / $input->pageSize)]),
            assignmentSignature: $signature,
        );
    }
}
