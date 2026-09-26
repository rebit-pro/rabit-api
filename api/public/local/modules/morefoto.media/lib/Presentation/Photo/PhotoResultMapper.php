<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo;

use Morefoto\Media\Application\Photo\Dto\AssignmentMutationOutputDto;
use Morefoto\Media\Application\Photo\Dto\CoverMutationOutputDto;
use Morefoto\Media\Application\Photo\Dto\DeletionMutationOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoOutputDto;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoOutputDto;
use Morefoto\Media\Presentation\Photo\Dto\GroupCoverResultDto;
use Morefoto\Media\Presentation\Photo\Dto\PhotoAssignmentsResultDto;
use Morefoto\Media\Presentation\Photo\Dto\PhotoDeletionResultDto;
use Morefoto\Media\Presentation\Photo\Dto\PhotoResultDto;
use Morefoto\Media\Presentation\Photo\Dto\UploadPhotoResultDto;

final readonly class PhotoResultMapper
{
    public function upload(UploadPhotoOutputDto $output): UploadPhotoResultDto
    {
        return new UploadPhotoResultDto(
            id: $output->id,
            status: $output->status,
            revision: $output->revision,
            existingPhotoId: $output->existingPhotoId,
            childCodes: $output->childCodes,
        );
    }

    public function photo(PhotoOutputDto $output): PhotoResultDto
    {
        return new PhotoResultDto(
            id: $output->id,
            status: $output->status,
            shootId: $output->shootId,
            groupId: $output->groupId,
            originalGroupId: $output->originalGroupId,
            childCode: $output->childCode,
            code: $output->code,
            sequence: $output->sequence,
            assignments: $output->assignments,
            filename: $output->filename,
            bytes: $output->bytes,
            width: $output->width,
            height: $output->height,
            fingerprint: $output->fingerprint,
            revision: $output->revision,
            thumbSrc: $output->thumbSrc,
            previewSrc: $output->previewSrc,
            error: $output->error,
            existingPhotoId: $output->existingPhotoId,
        );
    }

    public function assignment(AssignmentMutationOutputDto $output): PhotoAssignmentsResultDto
    {
        return new PhotoAssignmentsResultDto(
            photoIds: $output->photoIds,
            childCode: $output->childCode,
            childId: $output->childId,
            revision: $output->revision,
        );
    }

    public function cover(CoverMutationOutputDto $output): GroupCoverResultDto
    {
        return new GroupCoverResultDto(photoId: $output->photoId, revision: $output->revision);
    }

    public function deletion(DeletionMutationOutputDto $output): PhotoDeletionResultDto
    {
        return new PhotoDeletionResultDto(deleted: $output->deleted, revision: $output->revision);
    }
}
