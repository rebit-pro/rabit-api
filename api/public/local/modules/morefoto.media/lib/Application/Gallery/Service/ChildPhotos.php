<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Service;

use Morefoto\Media\Application\Gallery\Mapper\GalleryAssignmentMapper;
use Morefoto\Media\Domain\Gallery\Repository\GalleryPhotoRepository;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Contracts\Media\BoundedChildPhotosInterface;

/** Выдаёт готовые назначения выбранных детей съёмки в их текущей группе, сохраняя правило состава после переноса.
 * Ограниченный контракт позволяет явно проверить полноту большого сохраняемого состава без изменения прежней выдачи.
 */
final readonly class ChildPhotos implements ChildPhotosInterface, BoundedChildPhotosInterface
{
    public function __construct(private GalleryPhotoRepository $photos, private GalleryAssignmentMapper $mapper) {}

    public function ready(int $shootId, array $childIds, ?int $limit = null): array
    {
        if (null !== $limit && (1 > $limit || BoundedChildPhotosInterface::MAX_ROWS < $limit)) {
            throw new \InvalidArgumentException('Invalid child photo projection limit.');
        }
        $ids = array_values(array_unique(array_filter($childIds, static fn(int $id): bool => 0 < $id)));
        if ([] === $ids) {
            return [];
        }
        $result = null === $limit ? $this->photos->children($shootId, $ids) : $this->photos->children($shootId, $ids, $limit);
        $photos = [];
        while (false !== ($row = $result->fetch())) {
            $photos[] = $this->mapper->fromRow($row);
        }

        return $photos;
    }
}
