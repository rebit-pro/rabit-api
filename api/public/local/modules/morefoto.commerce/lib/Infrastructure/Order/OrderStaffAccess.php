<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Order;

use Morefoto\Commerce\Application\Order\Contract\OrderStaffAccessInterface;
use Morefoto\Commerce\Application\Order\Dto\OrderStaffScopeOutputDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Переводит роль и назначения Access в область чтения заказов D08 и отказы Access — в коды commerce. */
final readonly class OrderStaffAccess implements OrderStaffAccessInterface
{
    public function __construct(private InstitutionAccessInterface $institutions) {}

    public function scope(int $actorId): OrderStaffScopeOutputDto
    {
        try {
            $scope = $this->institutions->scope($actorId);
        } catch (HttpException $error) {
            throw match ($error->getCode()) {
                401 => new HttpException('UNAUTHORIZED', 401, $error),
                403 => new HttpException('FORBIDDEN', 403, $error),
                default => $error,
            };
        }

        $institutionIds = array_values(array_unique(array_map('intval', $scope->institutionIds)));
        sort($institutionIds);

        return match ($scope->role) {
            'organizer' => new OrderStaffScopeOutputDto($scope->role, $scope->accessRevision, null),
            'curator' => new OrderStaffScopeOutputDto($scope->role, $scope->accessRevision, $institutionIds),
            default => throw new HttpException('FORBIDDEN', 403),
        };
    }

    public function assertUnchanged(int $actorId, OrderStaffScopeOutputDto $scope): void
    {
        $current = $this->scope($actorId);
        if ($current->role !== $scope->role || $current->accessRevision !== $scope->accessRevision || $current->institutionIds !== $scope->institutionIds) {
            throw new HttpException('ACCESS_CHANGED', 409);
        }
    }
}
