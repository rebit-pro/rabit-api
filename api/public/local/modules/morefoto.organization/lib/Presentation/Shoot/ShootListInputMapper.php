<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Shoot;

use Morefoto\Organization\Application\Structure\Dto\ShootListInputDto;
use Morefoto\Organization\Domain\Structure\Enum\ShootSortEnum;
use Morefoto\Organization\Presentation\Shoot\Dto\ShootListRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Stateless mapping of the shoot list query; the newest shoots come first unless the cabinet asks otherwise. */
final readonly class ShootListInputMapper
{
    public function list(ShootListRequestDto $request): ShootListInputDto
    {
        if (1 > $request->page || 1000000 < $request->page || 1 > $request->pageSize || 100 < $request->pageSize) {
            throw new HttpException('INVALID_PAGE', 422);
        }
        $query = trim($request->q ?? '');
        if (100 < mb_strlen($query)) {
            throw new HttpException('INVALID_QUERY', 422);
        }
        if (null !== $request->direction && !in_array($request->direction, ['asc', 'desc'], true)) {
            throw new HttpException('INVALID_SORT', 422);
        }
        $sort = null === $request->sort
            ? ShootSortEnum::DATE
            : (ShootSortEnum::tryFrom($request->sort) ?? throw new HttpException('INVALID_SORT', 422));

        return new ShootListInputDto(
            query: $query,
            page: $request->page,
            pageSize: $request->pageSize,
            sort: $sort,
            descending: null === $request->direction ? ShootSortEnum::DATE === $sort : 'desc' === $request->direction,
        );
    }

    /** The use case re-resolves the token after the read, so a session revoked meanwhile gets no page. */
    public function bearer(ShootListRequestDto $request): string
    {
        return substr($request->authorization, 7);
    }
}
