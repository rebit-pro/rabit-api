<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Service;

use Morefoto\Media\Application\Photo\Dto\PhotoAssignmentOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoOutputDto;

final readonly class PhotoRowMapper
{
    /** @param array<string,mixed> $row */
    public function map(array $row): PhotoOutputDto
    {
        $assignments = [];
        foreach (array_filter(explode(',', (string)($row['ASSIGNMENTS'] ?? ''))) as $encoded) {
            $parts = explode(':', $encoded, 3);
            if (3 !== count($parts)) {
                continue;
            }
            $sequence = (int)$parts[2];
            $assignments[] = new PhotoAssignmentOutputDto(
                childId: $parts[0],
                childCode: $parts[1],
                sequence: $sequence,
                code: $parts[1] . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT),
            );
        }
        $primary = $assignments[0] ?? null;

        return new PhotoOutputDto(
            id: (string)$row['UF_PUBLIC_ID'],
            status: (string)$row['UF_STATUS'],
            shootId: (string)$row['SHOOT_PUBLIC_ID'],
            groupId: (string)$row['GROUP_PUBLIC_ID'],
            originalGroupId: (string)$row['ORIGINAL_GROUP_PUBLIC_ID'],
            childCode: $primary?->childCode,
            code: $primary?->code,
            sequence: $primary?->sequence,
            assignments: $assignments,
            filename: (string)$row['UF_FILENAME'],
            bytes: (int)$row['UF_BYTES'],
            width: (int)$row['UF_WIDTH'],
            height: (int)$row['UF_HEIGHT'],
            fingerprint: (string)$row['UF_FINGERPRINT'],
            revision: (int)$row['UF_REVISION'],
            thumbSrc: null === ($row['UF_THUMB_SRC'] ?? null) ? null : (string)$row['UF_THUMB_SRC'],
            previewSrc: null === ($row['UF_PREVIEW_SRC'] ?? null) ? null : (string)$row['UF_PREVIEW_SRC'],
            error: null === ($row['UF_ERROR_CODE'] ?? null) ? null : (string)$row['UF_ERROR_CODE'],
            existingPhotoId: null === ($row['EXISTING_PUBLIC_ID'] ?? null) ? null : (string)$row['EXISTING_PUBLIC_ID'],
        );
    }
}
