<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Dto\PhotoOutputDto;
use Morefoto\Media\Application\Photo\Service\PhotoRowMapper;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class GetPhotoUseCase
{
    public function __construct(
        private PhotoRepository $photos,
        private PhotoRowMapper $mapper,
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
    ) {}

    public function execute(int $userId, string $photoId): PhotoOutputDto
    {
        $row = $this->photos->photo($photoId)->fetch();
        if (!is_array($row)) {
            throw new HttpException('PHOTO_NOT_FOUND', 404);
        }
        $scope = $this->scopes->resolve((string)$row['SHOOT_PUBLIC_ID'], (string)$row['GROUP_PUBLIC_ID']);
        $this->access->assertCan($userId, 'media.manage', $scope->institutionId, $scope->groupId);

        return $this->mapper->map($row);
    }
}
