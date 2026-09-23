<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Service;

use Morefoto\Access\Domain\Staff\Enum\AccountStatusEnum;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;

/**
 * Раскладывает сотрудников на плитки-фильтры по статусу учётки и по роли. Каждая разбивка учитывает все выбранные
 * фильтры, кроме своего, поэтому плитка показывает, сколько сотрудников останется в списке после нажатия на неё.
 */
final readonly class StaffCountFacets
{
    /**
     * @param list<array{role: string, accountStatus: string, total: int}> $counts
     *
     * @return array{
     *     byAccountStatus: array<value-of<AccountStatusEnum>, int>,
     *     byRole: array<value-of<RoleEnum>, int>,
     * }
     */
    public function split(array $counts, ?RoleEnum $role, ?AccountStatusEnum $accountStatus): array
    {
        $byAccountStatus = array_fill_keys(array_map(static fn(AccountStatusEnum $case): string => $case->value, AccountStatusEnum::cases()), 0);
        $byRole = array_fill_keys(array_map(static fn(RoleEnum $case): string => $case->value, RoleEnum::cases()), 0);
        foreach ($counts as $count) {
            if (array_key_exists($count['accountStatus'], $byAccountStatus) && (null === $role || $role->value === $count['role'])) {
                $byAccountStatus[$count['accountStatus']] += $count['total'];
            }
            if (array_key_exists($count['role'], $byRole) && (null === $accountStatus || $accountStatus->value === $count['accountStatus'])) {
                $byRole[$count['role']] += $count['total'];
            }
        }

        return ['byAccountStatus' => $byAccountStatus, 'byRole' => $byRole];
    }
}
