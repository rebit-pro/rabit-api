<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\Handoff;

use Bitrix\Main\Application;
use Rebit\Share\Contracts\Media\Dto\StaffChildOutputDto;
use Rebit\Share\Contracts\Media\StaffChildReferenceInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Связывает введённый сотрудником код ребёнка или снимка с данными Media.
 *
 * Возвращает только готовые фотографии из указанной съёмки и группы, не раскрывая записи за пределами переданного scope.
 */
final readonly class StaffChildReference implements StaffChildReferenceInterface
{
    public function resolve(int $shootId, int $groupId, string $code): StaffChildOutputDto
    {
        $code = strtoupper(trim($code));
        if (1 !== preg_match('/^([A-Z]{1,3})([0-9]{3})?$/D', $code, $matches)) {
            throw new HttpException('CHILD_NOT_FOUND', 422);
        }
        $childCode = $matches[1];
        $sequence = isset($matches[2]) ? (int)$matches[2] : null;
        $connection = Application::getConnection();
        $child = $connection->query(
            "SELECT ID,PUBLIC_ID,CODE FROM mf_media_child WHERE SHOOT_ID={$shootId} AND GROUP_ID={$groupId} AND CODE='{$childCode}' LIMIT 1 FOR UPDATE",
        )->fetch();
        if (!is_array($child)) {
            throw new HttpException('CHILD_NOT_FOUND', 422);
        }
        $result = $connection->query(
            'SELECT p.UF_PUBLIC_ID,a.SEQUENCE_NO FROM mf_photo_assignment a '
            . 'INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID '
            . 'WHERE a.CHILD_ID=' . (int)$child['ID'] . " AND p.UF_STATUS='ready' ORDER BY a.SEQUENCE_NO,p.ID",
        );
        $photos = [];
        $matchedSequence = null === $sequence;
        while (false !== ($row = $result->fetch())) {
            $photos[] = (string)$row['UF_PUBLIC_ID'];
            $matchedSequence = $matchedSequence || $sequence === (int)$row['SEQUENCE_NO'];
        }
        if ([] === $photos || !$matchedSequence) {
            throw new HttpException('CHILD_NOT_FOUND', 422);
        }

        return new StaffChildOutputDto((int)$child['ID'], (string)$child['PUBLIC_ID'], (string)$child['CODE'], $photos);
    }
}
