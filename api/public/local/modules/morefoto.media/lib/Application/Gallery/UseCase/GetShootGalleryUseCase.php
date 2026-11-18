<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\UseCase;

use Morefoto\Media\Application\Gallery\Contract\PreviewContentInterface;
use Morefoto\Media\Application\Gallery\Dto\ShootGalleryOutputDto;
use Morefoto\Media\Domain\Gallery\Repository\ShootGalleryRepository;
use Morefoto\Media\Domain\Gallery\Service\GalleryAvailability;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Показывает по общей ссылке открытые группы выбранной съёмки. При выдаче обложки повторно проверяет доступ и принадлежность кадра. */
final readonly class GetShootGalleryUseCase
{
    public function __construct(
        private ShootGalleryRepository $repository,
        private GroupDirectoryInterface $groups,
        private MediaScopeInterface $scopes,
        private GalleryAvailability $availability,
        private ClockInterface $clock,
        private PreviewContentInterface $content,
    ) {}

    public function execute(string $key): ShootGalleryOutputDto
    {
        $shootId = $this->repository->shoot($key);
        $scope = $this->scopes->resolve($shootId);
        $now = $this->clock->now();
        $tiles = [];
        $number = 1;
        do {
            $page = $this->groups->page(new GroupDirectoryQueryInputDto(null, null, null, $shootId, null, $number, 100));
            $available = array_filter($page->items, fn(GroupDirectoryItemOutputDto $group): bool => $this->visible($group, $now));
            $keys = $this->repository->groupLinks(array_column($available, 'id'));
            $visibleIds = [];
            foreach ($available as $group) {
                if (isset($keys[$group->id])) {
                    $visibleIds[] = $group->nativeId;
                }
            }
            $covers = [] === $visibleIds ? [] : $this->repository->covers($scope->shootId, $visibleIds);
            foreach ($available as $group) {
                if (isset($keys[$group->id])) {
                    $tiles[] = ['id' => $group->id, 'name' => $group->name, 'key' => $keys[$group->id], 'hasCover' => isset($covers[$group->nativeId])];
                }
            }
        } while (100 * $number++ < $page->total);

        return new ShootGalleryOutputDto($scope->shootName, $scope->institutionName, $tiles);
    }

    public function cover(string $key, string $groupId): PreviewContentOutputDto
    {
        $shootId = $this->repository->shoot($key);
        $group = $this->groups->find($groupId);
        if (null === $group || $group->shootId !== $shootId || !$this->visible($group, $this->clock->now())
            || !isset($this->repository->groupLinks([$groupId])[$groupId])) {
            throw new HttpException('PHOTO_NOT_FOUND', 404);
        }
        $photo = $this->repository->covers($group->shootNativeId, [$group->nativeId])[$group->nativeId] ?? throw new HttpException('PHOTO_NOT_FOUND', 404);

        return $this->content->read($photo, 'thumb');
    }

    private function visible(GroupDirectoryItemOutputDto $group, \DateTimeImmutable $now): bool
    {
        $calendar = $group->calendar;

        return 'regular' === $group->kind && 'preparing' !== $this->availability->state($calendar->sentAt, $calendar->closesAt, $calendar->digitalSalesUntil, $now);
    }
}
