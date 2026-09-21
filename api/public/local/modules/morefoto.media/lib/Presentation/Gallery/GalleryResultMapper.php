<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Gallery;

use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Morefoto\Media\Presentation\Gallery\Dto\GalleryResultDto;

final readonly class GalleryResultMapper
{
    public function map(GalleryAccessOutputDto $output, string $token): GalleryResultDto
    {
        $children = [];
        foreach ($output->assignments as $photo) {
            $children[$photo->childId] ??= ['code' => $photo->childCode, 'photos' => []];
            $base = '/api/v1/public/galleries/' . $token . '/photos/' . $photo->assignmentId;
            $children[$photo->childId]['photos'][] = [
                'id' => $photo->photoId, 'assignmentId' => $photo->assignmentId, 'code' => $photo->code,
                'thumbSrc' => $base . '/thumb', 'previewSrc' => $base . '/preview',
                'width' => $photo->width, 'height' => $photo->height,
            ];
        }
        $group = $output->group;

        return new GalleryResultDto(
            $group->publicId,
            $group->institutionName,
            $group->groupName,
            $group->shootName,
            $group->kind,
            $output->state,
            $this->date($group->sentAt),
            $this->date($group->closesAt),
            $output->referenceNow,
            array_values($children),
            '',
            'Срок передачи фотографий уточняйте у куратора.',
        );
    }

    private function date(?string $date): ?string
    {
        return null === $date ? null : (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
