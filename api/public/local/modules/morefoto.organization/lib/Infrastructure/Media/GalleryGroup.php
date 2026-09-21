<?php

declare(strict_types=1);

namespace Morefoto\Organization\Infrastructure\Media;

use Bitrix\Main\Application;
use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;
use Rebit\Share\Contracts\Organization\GalleryGroupInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Morefoto\Organization\Domain\Structure\Exception\StructureStorageException;

final readonly class GalleryGroup implements GalleryGroupInterface
{
    public function get(string $groupId): GalleryGroupOutputDto
    {
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $groupId)) {
            throw new HttpException('GALLERY_NOT_FOUND', 404);
        }
        try {
            $row = Application::getConnection()->query("SELECT g.ID,g.UF_PUBLIC_ID,g.UF_NAME,g.UF_KIND,g.UF_REVISION,
                DATE_FORMAT(g.UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS SENT_AT,
                DATE_FORMAT(g.UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS CLOSES_AT,
                s.ID AS SHOOT_ID,s.UF_NAME AS SHOOT_NAME,i.UF_NAME AS INSTITUTION_NAME
                FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID
                INNER JOIN b_hlbd_mf_institution i ON i.ID=s.UF_INSTITUTION_ID
                WHERE g.UF_PUBLIC_ID='{$groupId}'")->fetch();
        } catch (\Throwable $error) {
            throw new StructureStorageException('Cannot resolve gallery group.', 0, $error);
        }
        if (false === $row) {
            throw new HttpException('GALLERY_NOT_FOUND', 404);
        }

        return new GalleryGroupOutputDto(
            (int)$row['ID'],
            (int)$row['SHOOT_ID'],
            (string)$row['UF_PUBLIC_ID'],
            (string)$row['INSTITUTION_NAME'],
            (string)$row['SHOOT_NAME'],
            (string)$row['UF_NAME'],
            (string)$row['UF_KIND'],
            null === $row['SENT_AT'] ? null : (string)$row['SENT_AT'],
            null === $row['CLOSES_AT'] ? null : (string)$row['CLOSES_AT'],
            (int)$row['UF_REVISION'],
        );
    }
}
