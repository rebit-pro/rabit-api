<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo;

use Morefoto\Media\Application\Photo\Dto\PhotoPageOutputDto;
use Morefoto\Media\Presentation\Photo\Dto\PhotoGroupSummaryResultDto;
use Morefoto\Media\Presentation\Photo\Dto\PhotoPageResultDto;

final readonly class PhotoListResultMapper
{
    public function page(PhotoPageOutputDto $output): PhotoPageResultDto
    {
        $summary = $output->summary;

        return new PhotoPageResultDto(
            items: $output->items,
            groups: $output->groups,
            covers: $output->covers,
            revision: $output->revision,
            meta: $output->meta,
            summary: null === $summary ? null : new PhotoGroupSummaryResultDto(
                photos: $summary->photos,
                unassigned: $summary->unassigned,
                children: $summary->children,
            ),
        );
    }
}
