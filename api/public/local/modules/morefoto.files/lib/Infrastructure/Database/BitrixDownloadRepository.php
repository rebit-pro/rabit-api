<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Files\Domain\Download\Enum\DownloadKindEnum;
use Morefoto\Files\Domain\Download\Enum\DownloadStatusEnum;
use Morefoto\Files\Domain\Download\Exception\DuplicateDownloadException;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Morefoto\Files\Domain\Download\ValueObject\Download;

/**
 * Моменты хранятся в UTC; ACTIVE_ORDER_ID — замок единственной pending-сборки ZIP заказа.
 *
 * @phpstan-type DownloadRow array{
 *     ID: int|string,
 *     PUBLIC_ID: string,
 *     ORDER_ID: int|string,
 *     KIND: string,
 *     STATUS: string,
 *     PHOTO_IDS: string,
 *     COMPOSITION_HASH: string,
 *     REQUEST_HASH: string,
 *     FILENAME: string,
 *     ARCHIVE_PATH: null|string,
 *     BYTES: null|int|string,
 *     ERROR_CODE: null|string,
 *     ATTEMPTS: int|string,
 *     NEXT_ATTEMPT_AT: null|string,
 *     EXPIRES_AT: null|string,
 * }
 */
final readonly class BitrixDownloadRepository implements DownloadRepositoryInterface
{
    private const string COLUMNS = "ID,PUBLIC_ID,ORDER_ID,KIND,STATUS,PHOTO_IDS,COMPOSITION_HASH,REQUEST_HASH,FILENAME,ARCHIVE_PATH,BYTES,ERROR_CODE,ATTEMPTS,
        DATE_FORMAT(NEXT_ATTEMPT_AT,'%Y-%m-%d %H:%i:%s') AS NEXT_ATTEMPT_AT,DATE_FORMAT(EXPIRES_AT,'%Y-%m-%d %H:%i:%s') AS EXPIRES_AT";

    public function insert(Download $download, string $idempotencyHash, \DateTimeImmutable $now): void
    {
        $connection = Application::getConnection();
        $text = $this->text(...);
        $locked = DownloadKindEnum::ZIP === $download->kind && DownloadStatusEnum::PENDING === $download->status;
        try {
            $connection->queryExecute('INSERT IGNORE INTO mf_file_download(PUBLIC_ID,ORDER_ID,KIND,STATUS,PHOTO_IDS,COMPOSITION_HASH,IDEMPOTENCY_HASH,
                REQUEST_HASH,ACTIVE_ORDER_ID,FILENAME,BYTES,ATTEMPTS,NEXT_ATTEMPT_AT,EXPIRES_AT,READY_AT,CREATED_AT,UPDATED_AT) VALUES(' . implode(',', [
                $text($download->publicId), $download->orderId, $text($download->kind->value), $text($download->status->value),
                $text(json_encode($download->photoIds, JSON_THROW_ON_ERROR)), $text($download->compositionHash), $text($idempotencyHash),
                $text($download->requestHash), $locked ? (string)$download->orderId : 'NULL', $text($download->filename),
                null === $download->bytes ? 'NULL' : (string)$download->bytes, '0', $this->moment($download->nextAttemptAt),
                $this->moment($download->expiresAt), DownloadStatusEnum::READY === $download->status ? $this->moment($now) : 'NULL',
                $this->moment($now), $this->moment($now),
            ]) . ')');
            $inserted = 1 === $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot persist download.', 0, $error);
        }
        if (!$inserted) {
            throw new DuplicateDownloadException('Idempotency key or archive lock is already taken.');
        }
    }

    public function find(string $publicId): ?Download
    {
        return $this->one('PUBLIC_ID=' . $this->text($publicId));
    }

    public function byIdempotency(int $orderId, string $idempotencyHash): ?Download
    {
        return $this->one("ORDER_ID={$orderId} AND IDEMPOTENCY_HASH=" . $this->text($idempotencyHash));
    }

    public function pendingArchive(int $orderId): ?Download
    {
        return $this->one("ACTIVE_ORDER_ID={$orderId}");
    }

    public function reusableArchive(int $orderId, string $compositionHash, \DateTimeImmutable $now): ?Download
    {
        return $this->one("ORDER_ID={$orderId} AND KIND='zip' AND STATUS='ready' AND COMPOSITION_HASH=" . $this->text($compositionHash)
            . ' AND EXPIRES_AT>' . $this->moment($now) . ' ORDER BY EXPIRES_AT DESC LIMIT 1');
    }

    public function claim(string $publicId, int $maxAttempts, \DateTimeImmutable $now, \DateTimeImmutable $leaseUntil): bool
    {
        return 1 === $this->execute('UPDATE mf_file_download SET ATTEMPTS=ATTEMPTS+1,NEXT_ATTEMPT_AT=' . $this->moment($leaseUntil)
            . ',UPDATED_AT=' . $this->moment($now) . ' WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='pending'"
            . " AND ATTEMPTS<{$maxAttempts} AND (ATTEMPTS=0 OR NEXT_ATTEMPT_AT<=" . $this->moment($now) . ')');
    }

    public function markReady(string $publicId, string $archivePath, int $bytes, \DateTimeImmutable $expiresAt): void
    {
        $this->execute("UPDATE mf_file_download SET STATUS='ready',ACTIVE_ORDER_ID=NULL,ARCHIVE_PATH=" . $this->text($archivePath)
            . ",BYTES={$bytes},NEXT_ATTEMPT_AT=NULL,EXPIRES_AT=" . $this->moment($expiresAt) . ',READY_AT=UTC_TIMESTAMP(),UPDATED_AT=UTC_TIMESTAMP()'
            . ' WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='pending'");
    }

    public function markRetry(string $publicId, \DateTimeImmutable $nextAttemptAt): void
    {
        $this->execute('UPDATE mf_file_download SET NEXT_ATTEMPT_AT=' . $this->moment($nextAttemptAt) . ',UPDATED_AT=UTC_TIMESTAMP()'
            . ' WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='pending'");
    }

    public function markFailed(string $publicId, string $errorCode, \DateTimeImmutable $expiresAt): void
    {
        $this->execute("UPDATE mf_file_download SET STATUS='failed',ACTIVE_ORDER_ID=NULL,NEXT_ATTEMPT_AT=NULL,ERROR_CODE=" . $this->text($errorCode)
            . ',EXPIRES_AT=' . $this->moment($expiresAt) . ',UPDATED_AT=UTC_TIMESTAMP() WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='pending'");
    }

    public function markExpired(string $publicId): void
    {
        $this->execute("UPDATE mf_file_download SET STATUS='expired',ARCHIVE_PATH=NULL,UPDATED_AT=UTC_TIMESTAMP() WHERE PUBLIC_ID="
            . $this->text($publicId) . " AND STATUS IN ('ready','failed')");
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        return $this->many("STATUS='pending' AND NEXT_ATTEMPT_AT<=" . $this->moment($now) . ' ORDER BY NEXT_ATTEMPT_AT LIMIT ' . max(1, $limit));
    }

    public function stale(\DateTimeImmutable $now, int $limit): array
    {
        return $this->many("STATUS IN ('ready','failed') AND EXPIRES_AT<=" . $this->moment($now) . ' ORDER BY EXPIRES_AT LIMIT ' . max(1, $limit));
    }

    private function one(string $where): ?Download
    {
        return $this->many($where)[0] ?? null;
    }

    /** @return list<Download> */
    private function many(string $where): array
    {
        try {
            $result = Application::getConnection()->query('SELECT ' . self::COLUMNS . ' FROM mf_file_download WHERE ' . $where);
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot read downloads.', 0, $error);
        }

        return $this->downloads($result);
    }

    /** @return list<Download> */
    private function downloads(Result $result): array
    {
        $downloads = [];
        while (false !== ($row = $result->fetch())) {
            /** @var DownloadRow $row */
            /** @var list<string> $photoIds */
            $photoIds = json_decode($row['PHOTO_IDS'], true, 2, JSON_THROW_ON_ERROR);
            $downloads[] = new Download(
                id: (int)$row['ID'],
                publicId: $row['PUBLIC_ID'],
                orderId: (int)$row['ORDER_ID'],
                kind: DownloadKindEnum::from($row['KIND']),
                status: DownloadStatusEnum::from($row['STATUS']),
                photoIds: $photoIds,
                compositionHash: $row['COMPOSITION_HASH'],
                requestHash: $row['REQUEST_HASH'],
                filename: $row['FILENAME'],
                archivePath: $row['ARCHIVE_PATH'],
                bytes: null === $row['BYTES'] ? null : (int)$row['BYTES'],
                errorCode: $row['ERROR_CODE'],
                attempts: (int)$row['ATTEMPTS'],
                nextAttemptAt: $this->date($row['NEXT_ATTEMPT_AT']),
                expiresAt: $this->date($row['EXPIRES_AT']),
            );
        }

        return $downloads;
    }

    private function execute(string $sql): int
    {
        try {
            $connection = Application::getConnection();
            $connection->queryExecute($sql);

            return $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot change download.', 0, $error);
        }
    }

    private function date(?string $value): ?\DateTimeImmutable
    {
        return null === $value ? null : new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private function moment(?\DateTimeImmutable $moment): string
    {
        return null === $moment ? 'NULL' : "'" . $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') . "'";
    }

    private function text(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }
}
