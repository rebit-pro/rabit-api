<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Service;

use Morefoto\Media\Application\Gallery\Mapper\GalleryAssignmentMapper;
use Morefoto\Media\Domain\Gallery\Repository\GalleryPhotoRepository;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;

/** Выдаёт служебным сценариям готовые кадры выбранных детей одной группы.
 * Нужен, чтобы карточка заказа предлагала кадры того же ребёнка без ссылок на изображения и без обхода Media.
 */
final readonly class ChildPhotos implements ChildPhotosInterface
{
    public function __construct(private GalleryPhotoRepository $photos, private GalleryAssignmentMapper $mapper) {}

    public function ready(int $groupId, int $shootId, array $childIds): array
    {
        $ids = array_values(array_unique(array_filter($childIds, static fn(int $id): bool => 0 < $id)));
        if ([] === $ids) {
            return [];
        }
        $result = $this->photos->children($groupId, $shootId, $ids);
        $photos = [];
        while (false !== ($row = $result->fetch())) {
            $photos[] = $this->mapper->fromRow($row);
        }

        return $photos;
    }
}
