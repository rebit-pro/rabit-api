<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Avatar;

use Morefoto\Access\Application\Avatar\Dto\UploadedAvatarInputDto;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;
use Morefoto\Access\Presentation\Avatar\Dto\AvatarImageRequestDto;
use Morefoto\Access\Presentation\Avatar\Dto\SaveMyAvatarRequestDto;
use Morefoto\Access\Presentation\Avatar\Dto\SaveStaffAvatarRequestDto;
use Morefoto\Access\Presentation\Avatar\Dto\StaffAvatarRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class AvatarInputMapper
{
    public const string USER_ID_PATTERN = '/^[1-9]\d{0,9}$/D';
    private const string VERSION_PATTERN = '/^[1-9]\d{0,9}$/D';

    public function upload(SaveMyAvatarRequestDto|SaveStaffAvatarRequestDto $request): UploadedAvatarInputDto
    {
        return new UploadedAvatarInputDto(tmpName: $request->tmpName, bytes: $request->bytes);
    }

    public function userId(AvatarImageRequestDto|SaveStaffAvatarRequestDto|StaffAvatarRequestDto $request): int
    {
        return (int)$request->userId;
    }

    public function variant(AvatarImageRequestDto $request): AvatarVariantEnum
    {
        return AvatarVariantEnum::from($request->variant);
    }

    /** A malformed version cannot match the current one, so it answers like an outdated link. */
    public function version(AvatarImageRequestDto $request): int
    {
        if (1 !== preg_match(self::VERSION_PATTERN, $request->v)) {
            throw new HttpException('AVATAR_NOT_FOUND', 404);
        }

        return (int)$request->v;
    }
}
