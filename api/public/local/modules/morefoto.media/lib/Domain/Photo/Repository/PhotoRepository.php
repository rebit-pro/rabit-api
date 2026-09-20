<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Photo\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Photo\Dto\InspectedPhoto;
use Morefoto\Media\Application\Photo\Dto\PhotoRegistration;
use Morefoto\Media\Application\Photo\Dto\PreviewOutputDto;
use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;

final readonly class PhotoRepository
{
    public function register(
        string $publicId,
        int $shootId,
        int $groupId,
        InspectedPhoto $photo,
        string $originalPath,
    ): PhotoRegistration {
        $dedupKey = $shootId . ':' . $photo->fingerprint;
        $values = [
            'publicId' => $publicId,
            'filename' => $photo->filename,
            'mimeType' => $photo->mimeType,
            'fingerprint' => $photo->fingerprint,
            'dedupKey' => $dedupKey,
            'originalPath' => $originalPath,
        ];
        try {
            $connection = Application::getConnection();
            $connection->queryExecute(sprintf(
                "INSERT INTO b_hlbd_mf_photo(UF_PUBLIC_ID,UF_SHOOT_ID,UF_GROUP_ID,UF_ORIGINAL_GROUP_ID,UF_FILENAME,UF_MIME_TYPE,UF_BYTES,UF_WIDTH,UF_HEIGHT,UF_FINGERPRINT,UF_DEDUP_KEY,UF_STATUS,UF_ORIGINAL_PATH,UF_JOB_STATE,UF_ATTEMPTS,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT)
                VALUES(%s,%d,%d,%d,%s,%s,%d,%d,%d,%s,%s,'processing',%s,'pending',0,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE ID=LAST_INSERT_ID(ID)",
                $this->quote($values['publicId']),
                $shootId,
                $groupId,
                $groupId,
                $this->quote($values['filename']),
                $this->quote($values['mimeType']),
                $photo->bytes,
                $photo->width,
                $photo->height,
                $this->quote($values['fingerprint']),
                $this->quote($values['dedupKey']),
                $this->quote($values['originalPath']),
            ));
            $canonical = $connection->query(
                'SELECT ID,UF_PUBLIC_ID,UF_STATUS,UF_REVISION FROM b_hlbd_mf_photo WHERE UF_DEDUP_KEY=' . $this->quote($dedupKey) . ' LIMIT 1',
            )->fetch();
            if (!is_array($canonical)) {
                throw new MediaStorageException('Cannot resolve photo deduplication result.');
            }
            if ((string)$canonical['UF_PUBLIC_ID'] === $publicId) {
                return new PhotoRegistration($publicId, 'processing', 1, true);
            }
            if ('failed' === (string)$canonical['UF_STATUS']) {
                $connection->queryExecute(
                    "UPDATE b_hlbd_mf_photo SET UF_STATUS='processing',UF_JOB_STATE='pending',UF_ATTEMPTS=0,"
                    . 'UF_ERROR_CODE=NULL,UF_REVISION=UF_REVISION+1,UF_UPDATED_AT=UTC_TIMESTAMP() WHERE ID='
                    . (int)$canonical['ID'] . " AND UF_STATUS='failed'",
                );

                return new PhotoRegistration((string)$canonical['UF_PUBLIC_ID'], 'processing', (int)$canonical['UF_REVISION'] + 1, true);
            }

            $connection->queryExecute(sprintf(
                "INSERT INTO b_hlbd_mf_photo(UF_PUBLIC_ID,UF_SHOOT_ID,UF_GROUP_ID,UF_ORIGINAL_GROUP_ID,UF_FILENAME,UF_MIME_TYPE,UF_BYTES,UF_WIDTH,UF_HEIGHT,UF_FINGERPRINT,UF_DEDUP_KEY,UF_STATUS,UF_ORIGINAL_PATH,UF_EXISTING_PHOTO_ID,UF_JOB_STATE,UF_ATTEMPTS,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT)
                VALUES(%s,%d,%d,%d,%s,%s,%d,%d,%d,%s,NULL,'duplicate',NULL,%d,'done',0,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())",
                $this->quote($publicId),
                $shootId,
                $groupId,
                $groupId,
                $this->quote($photo->filename),
                $this->quote($photo->mimeType),
                $photo->bytes,
                $photo->width,
                $photo->height,
                $this->quote($photo->fingerprint),
                (int)$canonical['ID'],
            ));

            return new PhotoRegistration($publicId, 'duplicate', 1, false, (string)$canonical['UF_PUBLIC_ID']);
        } catch (MediaStorageException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new MediaStorageException('Cannot register private photo.', 0, $error);
        }
    }

    public function photo(string $publicId): Result
    {
        return $this->query($this->baseSelect() . ' WHERE p.UF_PUBLIC_ID=' . $this->quote($publicId) . ' LIMIT 1');
    }

    public function photos(
        int $shootId,
        ?int $groupId,
        ?string $childCode,
        ?bool $assigned,
        int $limit,
        int $offset,
    ): Result {
        if (1 > $limit || 100 < $limit || 0 > $offset) {
            throw new \InvalidArgumentException('Invalid photo page.');
        }
        $pageCondition = $this->filterCondition($shootId, $groupId, $childCode, $assigned, 'page_photo');
        $condition = $this->filterCondition($shootId, $groupId, $childCode, $assigned, 'p');

        return $this->query(
            $this->baseSelect()
            . " INNER JOIN (SELECT page_photo.ID FROM b_hlbd_mf_photo page_photo WHERE {$pageCondition} ORDER BY page_photo.ID DESC LIMIT {$limit} OFFSET {$offset}) page ON page.ID=p.ID"
            . " WHERE {$condition} ORDER BY p.ID DESC",
        );
    }

    public function count(int $shootId, ?int $groupId, ?string $childCode, ?bool $assigned): int
    {
        $condition = $this->filterCondition($shootId, $groupId, $childCode, $assigned, 'p');
        $row = $this->query('SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_photo p WHERE ' . $condition)->fetch();

        return is_array($row) ? (int)$row['TOTAL'] : 0;
    }

    public function pendingJobs(int $limit): Result
    {
        if (1 > $limit || 500 < $limit) {
            throw new \InvalidArgumentException('Invalid pending job limit.');
        }

        return $this->query("SELECT UF_PUBLIC_ID,UF_REVISION FROM b_hlbd_mf_photo WHERE UF_STATUS='processing' AND UF_JOB_STATE='pending' ORDER BY ID LIMIT {$limit}");
    }

    public function markPublished(string $publicId): void
    {
        $this->execute("UPDATE b_hlbd_mf_photo SET UF_JOB_STATE='published',UF_UPDATED_AT=UTC_TIMESTAMP() WHERE UF_PUBLIC_ID="
            . $this->quote($publicId) . " AND UF_JOB_STATE='pending'");
    }

    public function markReady(string $publicId, PreviewOutputDto $preview): void
    {
        $this->execute("UPDATE b_hlbd_mf_photo SET UF_STATUS='ready',UF_JOB_STATE='done',UF_THUMB_SRC="
            . $this->quote($preview->thumbSrc) . ',UF_PREVIEW_SRC=' . $this->quote($preview->previewSrc)
            . ',UF_ERROR_CODE=NULL,UF_REVISION=UF_REVISION+1,UF_UPDATED_AT=UTC_TIMESTAMP() WHERE UF_PUBLIC_ID='
            . $this->quote($publicId) . " AND UF_STATUS='processing'");
    }

    public function markAttemptFailed(string $publicId): void
    {
        $this->execute('UPDATE b_hlbd_mf_photo SET UF_ATTEMPTS=UF_ATTEMPTS+1,'
            . "UF_STATUS=IF(UF_ATTEMPTS+1>=4,'failed','processing'),"
            . "UF_JOB_STATE=IF(UF_ATTEMPTS+1>=4,'failed','published'),"
            . "UF_ERROR_CODE=IF(UF_ATTEMPTS+1>=4,'PROCESSING_FAILED',NULL),"
            . 'UF_REVISION=UF_REVISION+1,UF_UPDATED_AT=UTC_TIMESTAMP() WHERE UF_PUBLIC_ID='
            . $this->quote($publicId) . " AND UF_STATUS='processing'");
    }

    public function originalPathInUse(string $path): bool
    {
        return false !== $this->query('SELECT ID FROM b_hlbd_mf_photo WHERE UF_ORIGINAL_PATH=' . $this->quote($path) . ' LIMIT 1')->fetch();
    }

    private function baseSelect(): string
    {
        return 'SELECT p.ID,p.UF_PUBLIC_ID,p.UF_FILENAME,p.UF_MIME_TYPE,p.UF_BYTES,p.UF_WIDTH,p.UF_HEIGHT,p.UF_FINGERPRINT,p.UF_STATUS,p.UF_ORIGINAL_PATH,p.UF_THUMB_SRC,p.UF_PREVIEW_SRC,p.UF_ERROR_CODE,p.UF_REVISION,'
            . 's.UF_PUBLIC_ID AS SHOOT_PUBLIC_ID,g.UF_PUBLIC_ID AS GROUP_PUBLIC_ID,og.UF_PUBLIC_ID AS ORIGINAL_GROUP_PUBLIC_ID,existing.UF_PUBLIC_ID AS EXISTING_PUBLIC_ID,'
            . "(SELECT GROUP_CONCAT(CONCAT(child.PUBLIC_ID,':',child.CODE,':',assignment.SEQUENCE_NO) ORDER BY assignment.SEQUENCE_NO,child.ID SEPARATOR ',') "
            . 'FROM mf_photo_assignment assignment INNER JOIN mf_media_child child ON child.ID=assignment.CHILD_ID '
            . 'WHERE assignment.PHOTO_ID=p.ID AND child.GROUP_ID=p.UF_GROUP_ID) AS ASSIGNMENTS '
            . 'FROM b_hlbd_mf_photo p INNER JOIN b_hlbd_mf_shoot s ON s.ID=p.UF_SHOOT_ID '
            . 'INNER JOIN b_hlbd_mf_group g ON g.ID=p.UF_GROUP_ID INNER JOIN b_hlbd_mf_group og ON og.ID=p.UF_ORIGINAL_GROUP_ID '
            . 'LEFT JOIN b_hlbd_mf_photo existing ON existing.ID=p.UF_EXISTING_PHOTO_ID';
    }

    private function filterCondition(
        int $shootId,
        ?int $groupId,
        ?string $childCode,
        ?bool $assigned,
        string $alias,
    ): string {
        $condition = "{$alias}.UF_SHOOT_ID={$shootId}";
        if (null !== $groupId) {
            $condition .= " AND {$alias}.UF_GROUP_ID={$groupId}";
        }
        $assignment = 'SELECT 1 FROM mf_photo_assignment filter_assignment '
            . 'INNER JOIN mf_media_child filter_child ON filter_child.ID=filter_assignment.CHILD_ID '
            . "WHERE filter_assignment.PHOTO_ID={$alias}.ID AND filter_child.GROUP_ID={$alias}.UF_GROUP_ID";
        if (null !== $childCode) {
            $condition .= ' AND EXISTS(' . $assignment . ' AND filter_child.CODE=' . $this->quote($childCode) . ')';
        }
        if (null !== $assigned) {
            $condition .= ($assigned ? ' AND EXISTS(' : ' AND NOT EXISTS(') . $assignment . ')';
        }

        return $condition;
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
            throw new MediaStorageException('Cannot read media state.', 0, $error);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $error) {
            throw new MediaStorageException('Cannot update media state.', 0, $error);
        }
    }
}
