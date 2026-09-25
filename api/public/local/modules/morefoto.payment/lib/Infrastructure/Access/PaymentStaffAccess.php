<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Access;

use Morefoto\Payment\Application\Payment\Contract\PaymentStaffAccessInterface;
use Morefoto\Payment\Application\Payment\Dto\PaymentStaffScopeOutputDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Переводит роль и назначения Access в область реестра платежей (как у COM-12) и отказы Access — в коды payment. */
final readonly class PaymentStaffAccess implements PaymentStaffAccessInterface
{
    public function __construct(private InstitutionAccessInterface $institutions) {}

    public function scope(int $actorId): PaymentStaffScopeOutputDto
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
            'organizer' => new PaymentStaffScopeOutputDto($scope->role, $scope->accessRevision, null),
            'curator' => new PaymentStaffScopeOutputDto($scope->role, $scope->accessRevision, $institutionIds),
            default => throw new HttpException('FORBIDDEN', 403),
        };
    }

    public function assertUnchanged(int $actorId, PaymentStaffScopeOutputDto $scope): void
    {
        $current = $this->scope($actorId);
        if ($current->role !== $scope->role || $current->accessRevision !== $scope->accessRevision || $current->institutionIds !== $scope->institutionIds) {
            throw new HttpException('ACCESS_CHANGED', 409);
        }
    }
}
