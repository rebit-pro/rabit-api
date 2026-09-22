<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Service;

use Morefoto\Handoff\Application\Request\Dto\StaffTransferPlanOutputDto;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Domain\Request\Service\StaffTransferPolicy;
use Rebit\Share\Contracts\Commerce\ChildOrdersInterface;
use Rebit\Share\Contracts\Media\ChildTransferInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaGroupOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Собирает проверяемое состояние льготного переноса заявки: строки, полные наборы Media, единственную staff-группу съёмки,
 * её свободные коды и факт заказов. Превью и подтверждение считают по нему одну и ту же подпись.
 */
final readonly class StaffTransferPlanner
{
    public function __construct(
        private StaffRequestRepository $requests,
        private MediaScopeInterface $scopes,
        private GroupDirectoryInterface $directory,
        private ChildTransferInterface $children,
        private ChildOrdersInterface $orders,
        private StaffTransferPolicy $policy,
    ) {}

    /** @return list<MediaGroupOutputDto> */
    public function staffGroups(string $shootId): array
    {
        return array_values(array_filter($this->scopes->groups($shootId), static fn(MediaGroupOutputDto $group): bool => 'staff' === $group->kind));
    }

    /**
     * С $lock вызывается внутри транзакции после блокировок Access и Organization: Media блокирует ревизию съёмки и строки детей
     * до проверки заказов, поэтому параллельное оформление заказа либо попадает в подпись, либо видит уже перенесённый набор.
     *
     * @param array<string, mixed> $request строка заявки из StaffRequestRepository::request()
     */
    public function plan(array $request, bool $lock): StaffTransferPlanOutputDto
    {
        $staff = $this->staffGroups((string)$request['SHOOT_PUBLIC_ID']);
        if ([] === $staff) {
            throw new HttpException('STAFF_GROUP_REQUIRED', 409);
        }
        if (1 < count($staff)) {
            throw new HttpException('STAFF_GROUP_AMBIGUOUS', 409);
        }
        $target = $staff[0];
        $status = $this->directory->find($target->publicId)?->calendar->status ?? throw new HttpException('STAFF_GROUP_REQUIRED', 409);
        $rows = $this->requests->transferRows((int)$request['ID']);
        $childIds = array_column($rows, 'childId');
        $shootId = (int)$request['SHOOT_ID'];
        $sets = $lock ? $this->children->lockSets($shootId, $childIds) : $this->children->sets($shootId, $childIds);
        $codes = $this->children->freeCodes($target->id, count($rows));
        $ordered = $this->orders->withOrders($childIds);

        return new StaffTransferPlanOutputDto(
            plan: $this->policy->plan((string)$request['PUBLIC_ID'], (int)$request['REVISION'], $target->publicId, $status, $rows, $sets, $codes, $ordered),
            targetGroupId: $target->id,
            targetGroupPublicId: $target->publicId,
            rows: $rows,
            sets: $sets,
        );
    }
}
