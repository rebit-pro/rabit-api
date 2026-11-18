<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Structure;

use Morefoto\Organization\Application\Structure\Dto\ImportGroupsInputDto;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureName;
use Morefoto\Organization\Presentation\Structure\Dto\ImportGroupsRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class ImportGroupsInputMapper
{
    public function map(ImportGroupsRequestDto $request): ImportGroupsInputDto
    {
        if (1 !== preg_match('/^[a-f0-9]{32}$/D', $request->key)) {
            throw new HttpException('INVALID_IDEMPOTENCY_KEY', 422);
        }
        if ([] === $request->groups) {
            throw new HttpException('INVALID_IMPORT_GROUPS', 422);
        }
        $groups = [];
        foreach ($request->groups as $folder) {
            if (1 === preg_match('/^\s*$/uD', $folder)) {
                throw new HttpException('INVALID_IMPORT_GROUPS', 422);
            }
            try {
                $groups[] = (new StructureName($folder))->value;
            } catch (\InvalidArgumentException) {
                throw new HttpException('INVALID_IMPORT_GROUPS', 422);
            }
        }

        return new ImportGroupsInputDto($request->institutionId, $request->shootId, $request->key, substr($request->authorization, 7), $groups);
    }
}
