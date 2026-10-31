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
 * Показывает в отчёте первый готовый кадр ребёнка как миниатюру или водяное превью только сотруднику своей области.
 * Кадр доступен после передачи ссылки группе, когда его уже могут видеть родители.
 */
final readonly class GetChildPreviewUseCase
{
    public function __construct(
        private SalesAccessInterface $access,
        private GroupDirectoryInterface $groups,
        private GroupChildrenInterface $media,
    ) {}

    public function execute(int $actorId, string $childId, string $variant = 'thumb'): PreviewContentOutputDto
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
        if (!in_array($variant, ['thumb', 'preview'], true)) {
            throw new HttpException('INVALID_PREVIEW_VARIANT', 422);
        }
        $preview = null === $child->photoId || null === $group->calendar->sentAt ? null : match ($variant) {
            'preview' => $this->media->preview($child->photoId),
            default => $this->media->thumbnail($child->photoId),
        };

        return $preview ?? throw new HttpException('PREVIEW_UNAVAILABLE', 404);
    }
}
