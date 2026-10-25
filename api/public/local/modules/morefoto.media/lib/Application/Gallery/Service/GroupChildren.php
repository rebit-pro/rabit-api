<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Service;

use Morefoto\Media\Application\Gallery\Contract\PreviewContentInterface;
use Morefoto\Media\Domain\Gallery\Repository\GroupChildRepository;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Contracts\Media\Dto\GroupChildOutputDto;
use Rebit\Share\Contracts\Media\GroupChildrenInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Отдаёт отчётам продаж детей разметки по их текущим группам: код и первый готовый кадр ребёнка, который служит
 * миниатюрой. Права сотрудника на группу здесь не проверяются — это делает модуль отчёта.
 */
final readonly class GroupChildren implements GroupChildrenInterface
{
    public function __construct(private GroupChildRepository $children, private PreviewContentInterface $previews) {}

    public function children(array $groupIds): array
    {
        $ids = $this->ids($groupIds);
        if ([] === $ids) {
            return [];
        }
        $result = $this->children->children($ids);
        $children = [];
        while (false !== ($row = $result->fetch())) {
            $children[] = $this->child($row);
        }

        return $children;
    }

    public function counts(array $groupIds): array
    {
        $ids = $this->ids($groupIds);
        if ([] === $ids) {
            return [];
        }
        $result = $this->children->counts($ids);
        $counts = [];
        while (false !== ($row = $result->fetch())) {
            $counts[(int)$row['GROUP_ID']] = (int)$row['CHILDREN'];
        }

        return $counts;
    }

    public function find(string $childId): ?GroupChildOutputDto
    {
        $row = $this->children->find($childId);

        return null === $row ? null : $this->child($row);
    }

    public function thumbnail(string $photoId): ?PreviewContentOutputDto
    {
        try {
            return $this->previews->read($photoId, 'thumb');
        } catch (HttpException) {
            return null;
        }
    }

    /**
     * @param list<int> $groupIds
     *
     * @return list<int>
     */
    private function ids(array $groupIds): array
    {
        return array_values(array_unique(array_filter($groupIds, static fn(int $id): bool => 0 < $id)));
    }

    /** @param array<string, mixed> $row */
    private function child(array $row): GroupChildOutputDto
    {
        $photoId = $row['PHOTO_ID'] ?? null;

        return new GroupChildOutputDto(
            nativeId: (int)$row['ID'],
            id: (string)$row['PUBLIC_ID'],
            groupId: (int)$row['GROUP_ID'],
            code: (string)$row['CODE'],
            photoId: null === $photoId || '' === $photoId ? null : (string)$photoId,
        );
    }
}
