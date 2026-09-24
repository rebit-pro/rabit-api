<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Dto\ListPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoGroupSummaryOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoPageOutputDto;
use Morefoto\Media\Application\Photo\Service\PhotoRowMapper;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

/**
 * Формирует защищённую страницу фотографий для рабочего пространства разметки.
 *
 * Применяет серверные фильтры группы, ребёнка, разметки и статуса, считает разбивку обработки всей съёмки или
 * выбранной группы, а для выбранной группы добавляет сводку по её готовым кадрам: сколько их, сколько без ребёнка
 * и какие коды детей заняты. Так экран работает с одной страницей и не выкачивает съёмку целиком.
 */
final readonly class ListPhotosUseCase
{
    private const string SUMMARY_STATUS = 'ready';

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
            $input->status,
            $input->pageSize,
            ($input->page - 1) * $input->pageSize,
        );
        while (false !== ($row = $result->fetch())) {
            $items[] = $this->mapper->map($row);
        }
        $total = $this->photos->count($scope->shootId, $scope->groupId, $input->childCode, $input->assigned, $input->status);

        return new PhotoPageOutputDto(
            items: $items,
            groups: $this->scopes->groups($shootId),
            covers: $this->media->covers($scope->shootId, $scope->groupId),
            revision: $this->media->revision($scope->shootId),
            meta: ['page' => $input->page, 'pageSize' => $input->pageSize, 'total' => $total],
            summary: null === $scope->groupId ? null : $this->summary($scope->shootId, $scope->groupId),
            stats: $this->photos->stats($scope->shootId, $scope->groupId),
        );
    }

    private function summary(int $shootId, int $groupId): PhotoGroupSummaryOutputDto
    {
        return new PhotoGroupSummaryOutputDto(
            photos: $this->photos->count($shootId, $groupId, null, null, self::SUMMARY_STATUS),
            unassigned: $this->photos->count($shootId, $groupId, null, false, self::SUMMARY_STATUS),
            children: $this->photos->childCodes($shootId, $groupId),
        );
    }
}
