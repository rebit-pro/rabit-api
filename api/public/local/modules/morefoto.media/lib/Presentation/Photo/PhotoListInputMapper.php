<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo;

use Morefoto\Media\Application\Photo\Dto\ListPhotosInputDto;
use Morefoto\Media\Presentation\Photo\Dto\ListPhotosRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class PhotoListInputMapper
{
    public const string ID_PATTERN = '/^[a-f0-9-]{36}$/D';
    private const array STATUSES = ['processing', 'ready', 'failed', 'duplicate'];

    public function list(ListPhotosRequestDto $request): ListPhotosInputDto
    {
        if (null !== $request->groupId && 1 !== preg_match(self::ID_PATTERN, $request->groupId)) {
            throw new HttpException('INVALID_GROUP', 422);
        }
        if (1 > $request->page || 1000000 < $request->page || 1 > $request->pageSize || 100 < $request->pageSize) {
            throw new HttpException('INVALID_PAGE', 422);
        }
        if (null !== $request->childCode && 1 !== preg_match('/^[A-Z]{1,3}$/D', $request->childCode)) {
            throw new HttpException('INVALID_CHILD_CODE', 422);
        }
        if (null !== $request->assigned && !in_array($request->assigned, ['true', 'false', '1', '0'], true)) {
            throw new HttpException('INVALID_ASSIGNED_FILTER', 422);
        }
        if (null !== $request->status && !in_array($request->status, self::STATUSES, true)) {
            throw new HttpException('INVALID_STATUS', 422);
        }

        return new ListPhotosInputDto(
            groupId: $request->groupId,
            page: $request->page,
            pageSize: $request->pageSize,
            childCode: $request->childCode,
            assigned: null === $request->assigned ? null : in_array($request->assigned, ['true', '1'], true),
            status: $request->status,
        );
    }
}
