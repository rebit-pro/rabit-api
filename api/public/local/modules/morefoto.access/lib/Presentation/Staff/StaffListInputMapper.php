<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Staff;

use Morefoto\Access\Application\Staff\Dto\ListStaffInputDto;
use Morefoto\Access\Domain\Staff\Enum\AccountStatusEnum;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Morefoto\Access\Presentation\Staff\Dto\StaffListRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class StaffListInputMapper
{
    private const string PAGE_PATTERN = '/^[1-9][0-9]{0,6}$/D';

    /**
     * @throws HttpException
     */
    public function list(StaffListRequestDto $request): ListStaffInputDto
    {
        foreach ([$request->page, $request->pageSize] as $value) {
            if (null !== $value && 1 !== preg_match(self::PAGE_PATTERN, $value)) {
                throw new HttpException('INVALID_PAGE', 422);
            }
        }
        $active = null === $request->active ? null : filter_var($request->active, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if (null !== $request->active && null === $active) {
            throw new HttpException('INVALID_ACTIVE', 422);
        }

        return new ListStaffInputDto(
            query: trim($request->q ?? ''),
            role: null === $request->role ? null : (RoleEnum::tryFrom($request->role) ?? throw new HttpException('INVALID_ROLE', 422)),
            active: $active,
            accountStatus: null === $request->accountStatus
                ? null
                : (AccountStatusEnum::tryFrom($request->accountStatus) ?? throw new HttpException('INVALID_ACCOUNT_STATUS', 422)),
            page: (int)($request->page ?? 1),
            pageSize: (int)($request->pageSize ?? 25),
        );
    }
}
