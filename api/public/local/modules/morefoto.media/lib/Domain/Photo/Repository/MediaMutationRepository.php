<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Photo\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class MediaMutationRepository
{
    public function revision(int $shootId): int
    {
        $row = $this->query('SELECT REVISION FROM mf_media_shoot_state WHERE SHOOT_ID=' . $shootId)->fetch();

        return false === $row ? 1 : (int)$row['REVISION'];
    }

    public function lockRevision(int $shootId): int
    {
        $this->execute("INSERT INTO mf_media_shoot_state(SHOOT_ID,REVISION,UPDATED_AT) VALUES({$shootId},1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE SHOOT_ID=SHOOT_ID");
        $row = $this->query('SELECT REVISION FROM mf_media_shoot_state WHERE SHOOT_ID=' . $shootId . ' FOR UPDATE')->fetch();
        if (false === $row) {
            throw new MediaStorageException('Cannot lock media revision.');
        }

        return (int)$row['REVISION'];
    }

    public function advanceRevision(int $shootId, int $current): int
    {
        $next = $current + 1;
        $this->execute("UPDATE mf_media_shoot_state SET REVISION={$next},UPDATED_AT=UTC_TIMESTAMP() WHERE SHOOT_ID={$shootId} AND REVISION={$current}");
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }

        return $next;
    }

    /** @return array<string,int> public ID to native ID */
    public function readyPhotos(int $shootId, int $groupId, array $publicIds): array
    {
        $quoted = implode(',', array_map($this->quote(...), $publicIds));
        $result = $this->query("SELECT ID,UF_PUBLIC_ID FROM b_hlbd_mf_photo WHERE UF_SHOOT_ID={$shootId} AND UF_GROUP_ID={$groupId} AND UF_STATUS='ready' AND UF_PUBLIC_ID IN ({$quoted}) FOR UPDATE");
        $photos = [];
        while (false !== ($row = $result->fetch())) {
            $photos[(string)$row['UF_PUBLIC_ID']] = (int)$row['ID'];
        }
        if (count($photos) !== count($publicIds)) {
            throw new HttpException('PHOTO_NOT_ASSIGNABLE', 409);
        }

        return $photos;
    }

    /** @return array{id:int,publicId:string} */
    public function child(int $shootId, int $groupId, string $code): array
    {
        $condition = "SHOOT_ID={$shootId} AND GROUP_ID={$groupId} AND CODE=" . $this->quote($code);
        $row = $this->query('SELECT ID,PUBLIC_ID FROM mf_media_child WHERE ' . $condition . ' FOR UPDATE')->fetch();
        if (false === $row) {
            $publicId = Uuid::uuid4()->toString();
            $this->execute('INSERT INTO mf_media_child(PUBLIC_ID,SHOOT_ID,GROUP_ID,CODE,REVISION,CREATED_AT,UPDATED_AT) VALUES('
                . $this->quote($publicId) . ",{$shootId},{$groupId}," . $this->quote($code) . ',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $row = ['ID' => Application::getConnection()->getInsertedId(), 'PUBLIC_ID' => $publicId];
        }

        return ['id' => (int)$row['ID'], 'publicId' => (string)$row['PUBLIC_ID']];
    }

    /** @param list<int> $photoIds */
    public function assign(int $childId, array $photoIds): bool
    {
        $existing = [];
        $result = $this->query('SELECT PHOTO_ID FROM mf_photo_assignment WHERE CHILD_ID=' . $childId
            . ' AND PHOTO_ID IN (' . implode(',', $photoIds) . ') FOR UPDATE');
        while (false !== ($row = $result->fetch())) {
            $existing[(int)$row['PHOTO_ID']] = true;
        }
        $row = $this->query('SELECT COALESCE(MAX(SEQUENCE_NO),0) AS LAST_SEQUENCE FROM mf_photo_assignment WHERE CHILD_ID=' . $childId . ' FOR UPDATE')->fetch();
        $sequence = false === $row ? 0 : (int)$row['LAST_SEQUENCE'];
        $changed = false;
        foreach ($photoIds as $photoId) {
            if (isset($existing[$photoId])) {
                continue;
            }
            ++$sequence;
            $assignmentId = Uuid::uuid4()->toString();
            $this->execute("INSERT INTO mf_photo_assignment(PHOTO_ID,CHILD_ID,SEQUENCE_NO,CREATED_AT,PUBLIC_ID) VALUES({$photoId},{$childId},{$sequence},UTC_TIMESTAMP(),'{$assignmentId}')");
            $changed = true;
        }

        return $changed;
    }

    public function assertCoverPhoto(int $shootId, int $groupId, string $photoId): int
    {
        $row = $this->query("SELECT p.ID FROM b_hlbd_mf_photo p
            INNER JOIN mf_photo_assignment a ON a.PHOTO_ID=p.ID
            INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID AND c.GROUP_ID={$groupId}
            WHERE p.UF_PUBLIC_ID=" . $this->quote($photoId) . " AND p.UF_SHOOT_ID={$shootId} AND p.UF_GROUP_ID={$groupId} AND p.UF_STATUS='ready' LIMIT 1 FOR UPDATE")->fetch();
        if (false === $row) {
            throw new HttpException('PHOTO_NOT_COVER_ELIGIBLE', 409);
        }

        return (int)$row['ID'];
    }

    public function setCover(int $groupId, int $photoId): bool
    {
        $row = $this->query('SELECT PHOTO_ID FROM mf_media_group_cover WHERE GROUP_ID=' . $groupId . ' FOR UPDATE')->fetch();
        if (false !== $row && $photoId === (int)$row['PHOTO_ID']) {
            return false;
        }
        $this->execute("INSERT INTO mf_media_group_cover(GROUP_ID,PHOTO_ID,UPDATED_AT) VALUES({$groupId},{$photoId},UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE PHOTO_ID=VALUES(PHOTO_ID),UPDATED_AT=UTC_TIMESTAMP()");

        return true;
    }

    /** @return array<string,string> group public ID to photo public ID */
    public function covers(int $shootId, ?int $groupId): array
    {
        $condition = "g.UF_SHOOT_ID={$shootId}" . (null === $groupId ? '' : " AND g.ID={$groupId}");
        $result = $this->query('SELECT g.UF_PUBLIC_ID AS GROUP_PUBLIC_ID,p.UF_PUBLIC_ID AS PHOTO_PUBLIC_ID '
            . 'FROM mf_media_group_cover cover INNER JOIN b_hlbd_mf_group g ON g.ID=cover.GROUP_ID '
            . 'INNER JOIN b_hlbd_mf_photo p ON p.ID=cover.PHOTO_ID WHERE ' . $condition);
        $covers = [];
        while (false !== ($row = $result->fetch())) {
            $covers[(string)$row['GROUP_PUBLIC_ID']] = (string)$row['PHOTO_PUBLIC_ID'];
        }

        return $covers;
    }

    /**
     * Locks the photos to delete; processing ones are refused so a late render cannot leave orphaned previews.
     *
     * @param non-empty-list<string> $publicIds
     *
     * @return non-empty-list<array{id: int, publicId: string, originalPath: null|string}>
     */
    public function deletablePhotos(int $shootId, int $groupId, array $publicIds): array
    {
        $quoted = implode(',', array_map($this->quote(...), $publicIds));
        $result = $this->query("SELECT ID,UF_PUBLIC_ID,UF_STATUS,UF_ORIGINAL_PATH FROM b_hlbd_mf_photo WHERE UF_SHOOT_ID={$shootId} AND UF_GROUP_ID={$groupId} AND UF_PUBLIC_ID IN ({$quoted}) FOR UPDATE");
        $photos = [];
        $processing = false;
        while (false !== ($row = $result->fetch())) {
            $processing = $processing || 'processing' === $row['UF_STATUS'];
            $photos[] = [
                'id' => (int)$row['ID'],
                'publicId' => (string)$row['UF_PUBLIC_ID'],
                'originalPath' => null === $row['UF_ORIGINAL_PATH'] ? null : (string)$row['UF_ORIGINAL_PATH'],
            ];
        }
        if ([] === $photos || count($photos) !== count($publicIds)) {
            throw new HttpException('PHOTO_NOT_DELETABLE', 409);
        }
        if ($processing) {
            throw new HttpException('PHOTO_PROCESSING', 409);
        }

        return $photos;
    }

    /**
     * Removes the photos with their duplicate records and child assignments; a lost cover falls back to the next labeled photo.
     *
     * @param non-empty-list<int> $photoIds
     */
    public function deletePhotos(array $photoIds): void
    {
        $ids = implode(',', $photoIds);
        $result = $this->query('SELECT GROUP_ID FROM mf_media_group_cover WHERE PHOTO_ID IN (' . $ids . ') FOR UPDATE');
        $groups = [];
        while (false !== ($row = $result->fetch())) {
            $groups[] = (int)$row['GROUP_ID'];
        }
        $this->execute('DELETE FROM b_hlbd_mf_photo WHERE UF_EXISTING_PHOTO_ID IN (' . $ids . ')');
        $this->execute('DELETE FROM mf_photo_assignment WHERE PHOTO_ID IN (' . $ids . ')');
        foreach ($groups as $groupId) {
            $candidate = $this->query("SELECT p.ID FROM mf_photo_assignment a INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID
                INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID
                WHERE c.GROUP_ID={$groupId} AND p.UF_GROUP_ID={$groupId} AND p.UF_STATUS='ready'
                ORDER BY CHAR_LENGTH(c.CODE),c.CODE,a.SEQUENCE_NO,p.ID LIMIT 1")->fetch();
            $this->execute(false === $candidate
                ? 'DELETE FROM mf_media_group_cover WHERE GROUP_ID=' . $groupId
                : 'UPDATE mf_media_group_cover SET PHOTO_ID=' . (int)$candidate['ID'] . ',UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID=' . $groupId);
        }
        $this->execute('DELETE FROM b_hlbd_mf_photo WHERE ID IN (' . $ids . ')');
    }

    public function idempotency(int $actorId, string $resource, IdempotencyKey $key): Result
    {
        return $this->query('SELECT PAYLOAD_HASH,RESULT_JSON FROM mf_media_idempotency WHERE ACTOR_ID=' . $actorId
            . ' AND RESOURCE_KEY=' . $this->quote($resource) . ' AND IDEMPOTENCY_KEY=' . $this->quote($key->value) . ' FOR UPDATE');
    }

    public function saveIdempotency(int $actorId, string $resource, IdempotencyKey $key, string $hash, string $result): void
    {
        $this->execute('INSERT INTO mf_media_idempotency(ACTOR_ID,RESOURCE_KEY,IDEMPOTENCY_KEY,PAYLOAD_HASH,RESULT_JSON,CREATED_AT) VALUES('
            . $actorId . ',' . $this->quote($resource) . ',' . $this->quote($key->value) . ',' . $this->quote($hash) . ',' . $this->quote($result) . ',UTC_TIMESTAMP())');
    }

    private function quote(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new MediaStorageException('Cannot read media assignment state.', 0, $error);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $error) {
            throw new MediaStorageException('Cannot update media assignment state.', 0, $error);
        }
    }
}
