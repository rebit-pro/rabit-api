<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Service;

use Morefoto\Media\Application\Photo\Dto\PhotoAssignmentOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoOutputDto;

/**
 * Преобразует строку репозитория фотографий в выходной DTO Application-слоя.
 *
 * Декодирует M:N-разметку и собирает совместимое основное назначение, метаданные файла и состояния обработки.
 */
final readonly class PhotoRowMapper
{
    /** @param array<string,mixed> $row */
    public function map(array $row): PhotoOutputDto
    {
        $decoded = json_decode((string)($row['ASSIGNMENTS'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Invalid photo assignments payload.');
        }

        /** @var array<int, array{assignment: PhotoAssignmentOutputDto, sortId: int}> $assignmentsWithOrder */
        $assignmentsWithOrder = [];
        foreach ($decoded as $assignmentRow) {
            if (
                !is_array($assignmentRow)
                || !isset(
                    $assignmentRow['childId'],
                    $assignmentRow['childCode'],
                    $assignmentRow['sequence'],
                    $assignmentRow['sortId'],
                )
            ) {
                throw new \UnexpectedValueException('Invalid photo assignment row.');
            }
            $sequence = (int)$assignmentRow['sequence'];
            $assignmentsWithOrder[] = [
                'assignment' => new PhotoAssignmentOutputDto(
                    childId: (string)$assignmentRow['childId'],
                    childCode: (string)$assignmentRow['childCode'],
                    sequence: $sequence,
                    code: (string)$assignmentRow['childCode'] . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT),
                ),
                'sortId' => (int)$assignmentRow['sortId'],
            ];
        }
        usort(
            $assignmentsWithOrder,
            static function(array $left, array $right): int {
                $sequenceOrder = $left['assignment']->sequence <=> $right['assignment']->sequence;

                return 0 !== $sequenceOrder ? $sequenceOrder : $left['sortId'] <=> $right['sortId'];
            },
        );
        $assignments = array_map(
            static fn(array $item): PhotoAssignmentOutputDto => $item['assignment'],
            $assignmentsWithOrder,
        );
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
            thumbSrc: null === ($row['UF_THUMB_SRC'] ?? null) ? null : '/api/v1/photos/' . (string)$row['UF_PUBLIC_ID'] . '/thumb',
            previewSrc: null === ($row['UF_PREVIEW_SRC'] ?? null) ? null : '/api/v1/photos/' . (string)$row['UF_PUBLIC_ID'] . '/preview',
            error: null === ($row['UF_ERROR_CODE'] ?? null) ? null : (string)$row['UF_ERROR_CODE'],
            existingPhotoId: null === ($row['EXISTING_PUBLIC_ID'] ?? null) ? null : (string)$row['EXISTING_PUBLIC_ID'],
        );
    }
}
