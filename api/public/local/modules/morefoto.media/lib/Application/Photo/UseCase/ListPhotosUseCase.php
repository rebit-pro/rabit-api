<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Dto\ListPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoPageOutputDto;
use Morefoto\Media\Application\Photo\Service\PhotoRowMapper;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

final readonly class ListPhotosUseCase
{
    public function __construct(
        private PhotoRepository $photos,
        private PhotoRowMapper $mapper,
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
    ) {}

    public function execute(int $userId, string $shootId, ListPhotosInputDto $input): PhotoPageOutputDto
    {
        $scope = $this->scopes->resolve($shootId, $input->groupId);
        $this->access->assertCan($userId, 'media.manage', $scope->institutionId, $scope->groupId);
        $items = [];
        $revision = 0;
        $total = 0;
        if (!$input->noMatch) {
            $result = $this->photos->photos(
                $scope->shootId,
                $scope->groupId,
                $input->pageSize,
                ($input->page - 1) * $input->pageSize,
            );
            while (false !== ($row = $result->fetch())) {
                $photo = $this->mapper->map($row);
                $items[] = $photo;
                $revision = max($revision, $photo->revision);
            }
            $total = $this->photos->count($scope->shootId, $scope->groupId);
        }

        return new PhotoPageOutputDto(
            items: $items,
            groups: $this->scopes->groups($shootId),
            covers: [],
            revision: $revision,
            meta: ['page' => $input->page, 'pageSize' => $input->pageSize, 'total' => $total],
        );
    }
}
