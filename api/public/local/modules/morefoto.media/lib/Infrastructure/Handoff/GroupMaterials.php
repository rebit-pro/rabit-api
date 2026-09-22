<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\Handoff;

use Bitrix\Main\Application;
use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Rebit\Share\Contracts\Media\Dto\GroupMaterialsOutputDto;
use Rebit\Share\Contracts\Media\GroupMaterialsInterface;

final readonly class GroupMaterials implements GroupMaterialsInterface
{
    public function __construct(private MediaMutationRepository $mutations) {}

    public function snapshots(array $groupIds): array
    {
        $ids = array_values(array_unique(array_filter($groupIds, static fn(int $id): bool => 0 < $id)));
        if ([] === $ids) {
            return [];
        }
        $in = implode(',', $ids);
        /** @var array<int, array{
         *     photos: list<array{0: string, 1: int, 2: string}>,
         *     assignments: list<array{0: string, 1: string}>,
         *     cover: ?string,
         *     ready: array<string, true>,
         *     assigned: array<string, true>,
         *     children: array<int, true>,
         *     processing: int,
         * }> $groups */
        $groups = [];
        foreach ($ids as $id) {
            $groups[$id] = ['photos' => [], 'assignments' => [], 'cover' => null, 'ready' => [], 'assigned' => [], 'children' => [], 'processing' => 0];
        }
        try {
            $connection = Application::getConnection();
            $photos = $connection->query("SELECT UF_GROUP_ID,UF_PUBLIC_ID,UF_REVISION,UF_STATUS FROM b_hlbd_mf_photo WHERE UF_GROUP_ID IN ({$in}) ORDER BY UF_GROUP_ID,ID");
            while (false !== ($row = $photos->fetch())) {
                $group = (int)$row['UF_GROUP_ID'];
                $photo = (string)$row['UF_PUBLIC_ID'];
                $status = (string)$row['UF_STATUS'];
                $groups[$group]['photos'][] = [$photo, (int)$row['UF_REVISION'], $status];
                if ('ready' === $status) {
                    $groups[$group]['ready'][$photo] = true;
                } elseif ('processing' === $status) {
                    ++$groups[$group]['processing'];
                }
            }
            $assignments = $connection->query("SELECT c.GROUP_ID,c.ID AS CHILD_ID,c.CODE,p.UF_PUBLIC_ID AS PHOTO_ID FROM mf_photo_assignment a
                INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID
                WHERE c.GROUP_ID IN ({$in}) ORDER BY c.GROUP_ID,c.CODE,p.ID");
            while (false !== ($row = $assignments->fetch())) {
                $group = (int)$row['GROUP_ID'];
                $photo = (string)$row['PHOTO_ID'];
                $groups[$group]['assignments'][] = [(string)$row['CODE'], $photo];
                $groups[$group]['assigned'][$photo] = true;
                if (isset($groups[$group]['ready'][$photo])) {
                    $groups[$group]['children'][(int)$row['CHILD_ID']] = true;
                }
            }
            $covers = $connection->query("SELECT cover.GROUP_ID,p.UF_PUBLIC_ID AS PHOTO_ID FROM mf_media_group_cover cover
                INNER JOIN b_hlbd_mf_photo p ON p.ID=cover.PHOTO_ID WHERE cover.GROUP_ID IN ({$in})");
            while (false !== ($row = $covers->fetch())) {
                $groups[(int)$row['GROUP_ID']]['cover'] = (string)$row['PHOTO_ID'];
            }
        } catch (\Throwable $error) {
            throw new MediaStorageException('Cannot read group materials.', 0, $error);
        }
        $result = [];
        foreach ($groups as $id => $group) {
            $result[$id] = new GroupMaterialsOutputDto(
                readyPhotos: count($group['ready']),
                processingPhotos: $group['processing'],
                unassignedPhotos: count(array_diff_key($group['ready'], $group['assigned'])),
                children: count($group['children']),
                fingerprint: hash('sha256', json_encode([$group['photos'], $group['assignments'], $group['cover']], JSON_THROW_ON_ERROR)),
            );
        }

        return $result;
    }

    public function lock(int $shootId, int $groupId): GroupMaterialsOutputDto
    {
        $this->mutations->lockRevision($shootId);

        return $this->snapshots([$groupId])[$groupId] ?? throw new \InvalidArgumentException('A positive group ID is required.');
    }
}
