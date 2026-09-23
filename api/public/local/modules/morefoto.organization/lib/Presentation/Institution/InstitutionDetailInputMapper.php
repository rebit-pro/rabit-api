<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Institution;

use Morefoto\Organization\Application\Institution\Dto\InstitutionDetailInputDto;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Morefoto\Organization\Presentation\Institution\Dto\InstitutionDetailRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class InstitutionDetailInputMapper
{
    public const string UUID_PATTERN = '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[1-5][a-fA-F0-9]{3}-[89abAB][a-fA-F0-9]{3}-[a-fA-F0-9]{12}$/D';
    private const string PAGE_PATTERN = '/^[1-9][0-9]{0,6}$/D';
    private const int MAX_PAGE_SIZE = 100;

    public function institutionId(InstitutionDetailRequestDto $request): InstitutionId
    {
        return new InstitutionId($request->institutionId);
    }

    /** The use case re-resolves the token after reading, so a session replaced mid-request is rejected. */
    public function bearer(InstitutionDetailRequestDto $request): string
    {
        return substr($request->authorization, 7);
    }

    /**
     * @throws HttpException
     */
    public function pages(InstitutionDetailRequestDto $request): InstitutionDetailInputDto
    {
        foreach ([$request->shootsPage, $request->groupsPage, $request->pageSize] as $value) {
            if (null !== $value && 1 !== preg_match(self::PAGE_PATTERN, $value)) {
                throw new HttpException('INVALID_PAGE', 422);
            }
        }
        if (null !== $request->pageSize && self::MAX_PAGE_SIZE < (int)$request->pageSize) {
            throw new HttpException('INVALID_PAGE', 422);
        }

        return new InstitutionDetailInputDto((int)($request->shootsPage ?? 1), (int)($request->groupsPage ?? 1), (int)($request->pageSize ?? 50));
    }
}
