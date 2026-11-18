<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Gallery;

use Morefoto\Media\Application\Gallery\Dto\ShootGalleryOutputDto;
use Morefoto\Media\Presentation\Gallery\Dto\ShootGalleryResultDto;

final readonly class ShootGalleryResultMapper
{
    public function map(ShootGalleryOutputDto $view, string $key): ShootGalleryResultDto
    {
        $tiles = [];
        foreach ($view->groups as $group) {
            $tiles[] = [
                'name' => $group['name'],
                'groupPath' => '/g/' . $group['key'],
                'coverUrl' => $group['hasCover'] ? '/api/v1/public/galleries/shoots/' . $key . '/groups/' . $group['id'] . '/cover' : null,
            ];
        }

        return new ShootGalleryResultDto($view->shootName, $view->institutionName, [] === $tiles ? 'preparing' : 'open', $tiles);
    }
}
