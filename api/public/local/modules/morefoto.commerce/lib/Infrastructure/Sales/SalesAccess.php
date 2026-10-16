<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Sales;

use Morefoto\Commerce\Application\Sales\Contract\SalesAccessInterface;
use Morefoto\Commerce\Application\Sales\Dto\SalesScopeOutputDto;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Переводит подтверждённые Access роль и назначения в область сводки продаж E9; Access перечитывается на каждый запрос. */
final readonly class SalesAccess implements SalesAccessInterface
{
    public function __construct(private StaffRequestAccessInterface $access) {}

    public function scope(int $actorId): SalesScopeOutputDto
    {
        $actor = $this->access->actor($actorId);

        return match ($actor->role) {
            'organizer' => new SalesScopeOutputDto($actor->role, null, null),
            'curator' => new SalesScopeOutputDto($actor->role, $actor->institutionIds, null),
            'teacher' => new SalesScopeOutputDto($actor->role, null, $actor->groupIds),
            default => throw new HttpException('FORBIDDEN', 403),
        };
    }
}
