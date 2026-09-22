<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Mapper;

use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;

final readonly class GalleryAssignmentMapper
{
    /** @param array{
     *     ASSIGNMENT_ID: string, PHOTO_ID: string, CHILD_ID: string, NATIVE_CHILD_ID: int|string, CODE: string,
     *     SEQUENCE_NO: int|string, UF_WIDTH: int|string, UF_HEIGHT: int|string, UF_REVISION: int|string,
     * } $row
     */
    public function fromRow(array $row): GalleryAssignmentOutputDto
    {
        return new GalleryAssignmentOutputDto(
            (string)$row['ASSIGNMENT_ID'],
            (string)$row['PHOTO_ID'],
            (string)$row['CHILD_ID'],
            (int)$row['NATIVE_CHILD_ID'],
            (string)$row['CODE'],
            (string)$row['CODE'] . str_pad((string)$row['SEQUENCE_NO'], 3, '0', STR_PAD_LEFT),
            (int)$row['UF_WIDTH'],
            (int)$row['UF_HEIGHT'],
            (int)$row['UF_REVISION'],
        );
    }
}
