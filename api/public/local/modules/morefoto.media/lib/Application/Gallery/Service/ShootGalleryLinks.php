<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Service;

use Morefoto\Media\Domain\Gallery\Repository\ShootGalleryRepository;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Media\ShootGalleryLinkInterface;

/** Выдаёт стабильный адрес съёмки и восстанавливает копируемые ссылки уже открытых групп со старым форматом ключа. */
final readonly class ShootGalleryLinks implements ShootGalleryLinkInterface
{
    public function __construct(private ShootGalleryRepository $repository, private GalleryLinkInterface $groups) {}

    public function current(string $shootId): ?string
    {
        return $this->repository->current($shootId);
    }

    public function ensure(string $shootId, array $publishedGroups): string
    {
        foreach ($this->repository->groupLinks($publishedGroups) as $id => $key) {
            if (null === $key) {
                $this->groups->ensure($id);
            }
        }

        return $this->repository->ensure($shootId);
    }
}
