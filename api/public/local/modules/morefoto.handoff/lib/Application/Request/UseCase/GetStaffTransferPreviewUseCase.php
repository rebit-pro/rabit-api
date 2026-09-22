<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\UseCase;

use Morefoto\Handoff\Application\Request\Dto\StaffTransferPreviewOutputDto;
use Morefoto\Handoff\Application\Request\Service\StaffTransferGuard;
use Morefoto\Handoff\Application\Request\Service\StaffTransferPlanner;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Показывает организатору или куратору, что и куда перенесёт подтверждение заявки сотрудника.
 * Возвращает полные текущие наборы детей с целевыми кодами staff-группы, факт заказов и подпись, которую проверит HND-11.
 */
final readonly class GetStaffTransferPreviewUseCase
{
    public function __construct(
        private StaffTransferGuard $guard,
        private StaffRequestRepository $requests,
        private StaffTransferPlanner $planner,
    ) {}

    public function execute(int $actorId, string $requestId): StaffTransferPreviewOutputDto
    {
        $actor = $this->guard->actor($actorId);
        $request = $this->requests->request($requestId) ?? throw new HttpException('STAFF_REQUEST_NOT_FOUND', 404);
        $this->guard->assertVisible($actor, $request);
        $this->guard->assertSubmitted($request);
        $planned = $this->planner->plan($request, false);

        return new StaffTransferPreviewOutputDto(
            targetGroupId: $planned->targetGroupPublicId,
            bundles: $planned->plan->bundles,
            signature: $planned->plan->signature,
            hasOrders: $planned->plan->hasOrders,
            revision: (int)$request['REVISION'],
        );
    }
}
