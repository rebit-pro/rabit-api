<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Dto\ListPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoPageOutputDto;
use Morefoto\Media\Application\Photo\Service\PhotoRowMapper;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

/**
 * Формирует защищённую страницу фотографий для рабочего пространства разметки.
 *
 * Применяет серверные фильтры и собирает назначения, обложки, revision, данные пагинации и разбивку обработки всей
 * съёмки или выбранной группы (сколько готово, в обработке, с ошибкой, дублей и готовых без ребёнка) в единый DTO.
 */
final readonly class ListPhotosUseCase
{
    public function __construct(
        private PhotoRepository $photos,
        private MediaMutationRepository $media,
        private PhotoRowMapper $mapper,
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
    ) {}

    public function execute(int $userId, string $shootId, ListPhotosInputDto $input): PhotoPageOutputDto
    {
        $scope = $this->scopes->resolve($shootId, $input->groupId);
        $this->access->assertCan($userId, 'media.manage', $scope->institutionId, $scope->groupId);
        $items = [];
        $result = $this->photos->photos(
            $scope->shootId,
            $scope->groupId,
            $input->childCode,
            $input->assigned,
            $input->pageSize,
            ($input->page - 1) * $input->pageSize,
        );
        while (false !== ($row = $result->fetch())) {
            $items[] = $this->mapper->map($row);
        }
        $total = $this->photos->count($scope->shootId, $scope->groupId, $input->childCode, $input->assigned);

        return new PhotoPageOutputDto(
            items: $items,
            groups: $this->scopes->groups($shootId),
            covers: $this->media->covers($scope->shootId, $scope->groupId),
            revision: $this->media->revision($scope->shootId),
            meta: ['page' => $input->page, 'pageSize' => $input->pageSize, 'total' => $total],
            stats: $this->photos->stats($scope->shootId, $scope->groupId),
        );
    }
}
