<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive;

use Morefoto\Production\Presentation\Archive\Request\Dto\CreateVersionRequestDto;
use Morefoto\Production\Presentation\Archive\Request\Dto\ProductionGroupsRequestDto;
use Morefoto\Production\Presentation\Archive\Request\Dto\RenewVersionRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class ProductionInputMapper
{
    public const string UUID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D';
    private const int MAX_GROUPS = 100;

    /** @return list<string> */
    public function groupIds(ProductionGroupsRequestDto $request): array
    {
        $ids = '' === $request->groupIds ? [] : array_values(array_unique(explode(',', $request->groupIds)));
        if ([] === $ids || self::MAX_GROUPS < count($ids)) {
            throw new HttpException('INVALID_GROUPS', 422, null, ['groupIds' => 'Pass 1 to 100 group IDs separated by commas.']);
        }
        foreach ($ids as $id) {
            if (1 !== preg_match(self::UUID_PATTERN, $id)) {
                throw new HttpException('INVALID_GROUPS', 422, null, ['groupIds' => 'Group IDs must be UUIDs.']);
            }
        }

        return $ids;
    }

    public function key(CreateVersionRequestDto|RenewVersionRequestDto $request): string
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/D', $request->idempotencyKey)) {
            throw new HttpException('INVALID_IDEMPOTENCY_KEY', 422);
        }

        return strtolower($request->idempotencyKey);
    }
}
