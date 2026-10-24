<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\UseCase;

use Morefoto\Commerce\Application\Sales\Contract\SalesAccessInterface;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Contracts\Media\GroupChildrenInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * COM-17: показывает в отчёте миниатюру ребёнка — первый готовый кадр — только сотруднику, чья область отчёта
 * включает группу ребёнка, и только после передачи ссылки группе, когда эти кадры уже видят родители.
 */
final readonly class GetChildPreviewUseCase
{
    public function __construct(
        private SalesAccessInterface $access,
        private GroupDirectoryInterface $groups,
        private GroupChildrenInterface $media,
    ) {}

    public function execute(int $actorId, string $childId): PreviewContentOutputDto
    {
        $scope = $this->access->scope($actorId);
        $child = $this->media->find($childId);
        if (null === $child || (null !== $scope->groupIds && !in_array($child->groupId, $scope->groupIds, true))) {
            throw new HttpException('CHILD_NOT_FOUND', 404);
        }
        $group = $this->groups->page(new GroupDirectoryQueryInputDto($scope->institutionIds, [$child->groupId], null, null, null, 1, 1))->items[0] ?? null;
        if (null === $group || $group->nativeId !== $child->groupId) {
            throw new HttpException('CHILD_NOT_FOUND', 404);
        }
        $preview = null === $child->photoId || null === $group->calendar->sentAt ? null : $this->media->thumbnail($child->photoId);

        return $preview ?? throw new HttpException('PREVIEW_UNAVAILABLE', 404);
    }
}
