<?php

declare(strict_types=1);

namespace Morefoto\Production\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Morefoto\Production\Domain\Archive\ValueObject\NewVersion;
use Morefoto\Production\Domain\Archive\ValueObject\ProductSummary;
use Morefoto\Production\Domain\Archive\ValueObject\ScheduleCursor;

/**
 * Моменты хранятся в UTC. Уникальные ключи версии (группа, срок, номер) и строки (группа, срок, строка заказа) делают
 * параллельные снимки безопасными: проигравший снимок откатывается целиком.
 *
 * @phpstan-type VersionRow array{
 *     ID: int|string, PUBLIC_ID: string, GROUP_ID: int|string, GROUP_PUBLIC_ID: string, INSTITUTION_ID: int|string,
 *     DEADLINE_AT: string, VERSION_NO: int|string, STATUS: string, LINES_COUNT: int|string, FILES_COUNT: int|string,
 *     PRINTS_COUNT: int|string, WAITING_ORDERS: int|string, ARCHIVE_PATH: string, BYTES: null|int|string,
 *     ERROR_CODE: null|string, ATTEMPTS: int|string, LINK_GENERATION: int|string, CREATED_AT: string,
 *     READY_AT: null|string, EXPIRES_AT: null|string, CREATED_BY: null|int|string,
 * }
 */
final readonly class BitrixArchiveRepository implements ArchiveRepositoryInterface
{
    private const string COLUMNS = "ID,PUBLIC_ID,GROUP_ID,GROUP_PUBLIC_ID,INSTITUTION_ID,DATE_FORMAT(DEADLINE_AT,'%Y-%m-%d %H:%i:%s') AS DEADLINE_AT,
        VERSION_NO,STATUS,LINES_COUNT,FILES_COUNT,PRINTS_COUNT,WAITING_ORDERS,ARCHIVE_PATH,BYTES,ERROR_CODE,ATTEMPTS,LINK_GENERATION,
        DATE_FORMAT(CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT,DATE_FORMAT(READY_AT,'%Y-%m-%d %H:%i:%s') AS READY_AT,
        DATE_FORMAT(EXPIRES_AT,'%Y-%m-%d %H:%i:%s') AS EXPIRES_AT,CREATED_BY";
    private const int LINE_CHUNK = 500;

    public function cursor(): ScheduleCursor
    {
        /** @var array{SINCE_AT: string, CURSOR_AT: string, CURSOR_GROUP_ID: int|string}|false $row */
        $row = $this->query("SELECT DATE_FORMAT(SINCE_AT,'%Y-%m-%d %H:%i:%s') AS SINCE_AT,DATE_FORMAT(CURSOR_AT,'%Y-%m-%d %H:%i:%s') AS CURSOR_AT,
            CURSOR_GROUP_ID FROM mf_production_schedule WHERE ID=1")->fetch();
        if (false === $row) {
            throw new ProductionStorageException('Apply the L1 production migration first: the schedule row is missing.');
        }

        return new ScheduleCursor($this->instant($row['SINCE_AT']), $this->instant($row['CURSOR_AT']), (int)$row['CURSOR_GROUP_ID']);
    }

    public function advance(\DateTimeImmutable $cursorAt, int $cursorGroupId): void
    {
        $this->execute('UPDATE mf_production_schedule SET CURSOR_AT=' . $this->moment($cursorAt) . ',CURSOR_GROUP_ID=' . $cursorGroupId
            . ',UPDATED_AT=UTC_TIMESTAMP() WHERE ID=1');
    }

    public function create(NewVersion $version): bool
    {
        $connection = Application::getConnection();
        $text = $this->text(...);
        $deadline = $this->moment($version->deadlineAt);
        $connection->startTransaction();
        try {
            $connection->queryExecute('INSERT IGNORE INTO mf_production_version(PUBLIC_ID,GROUP_ID,GROUP_PUBLIC_ID,INSTITUTION_ID,DEADLINE_AT,VERSION_NO,
                STATUS,LINES_COUNT,FILES_COUNT,PRINTS_COUNT,WAITING_ORDERS,ARCHIVE_PATH,ATTEMPTS,LINK_GENERATION,CREATED_BY,CREATED_AT,UPDATED_AT) VALUES('
                . implode(',', [
                    $text($version->publicId), $version->groupId, $text($version->groupPublicId), $version->institutionId, $deadline,
                    $version->number, $text($version->status->value), count($version->lines), $version->filesCount, $version->printsCount,
                    $version->waitingOrders, $text($version->archivePath), 0, 1, $version->createdBy ?? 'NULL',
                    $this->moment($version->createdAt), $this->moment($version->createdAt),
                ]) . ')');
            if (1 !== $connection->getAffectedRowsCount()) {
                $connection->rollbackTransaction();

                return false;
            }
            $versionId = (int)$connection->getInsertedId();
            foreach (array_chunk($version->lines, self::LINE_CHUNK) as $chunk) {
                $values = [];
                foreach ($chunk as $line) {
                    $values[] = '(' . implode(',', [
                        $versionId, $version->groupId, $deadline, $text($line->orderLineId), $text($line->orderId), $text($line->orderNumber),
                        $text($line->paidAt), $text($line->audience), $text($line->childCode), $text($line->photoId), $text($line->photoCode),
                        $text($line->productId), $text($line->productName), $text($line->productFormat), $line->printCount, $line->quantity,
                        $line->prints, $text($line->entry),
                    ]) . ')';
                }
                $connection->queryExecute('INSERT IGNORE INTO mf_production_line(VERSION_ID,GROUP_ID,DEADLINE_AT,ORDER_LINE_ID,ORDER_PUBLIC_ID,ORDER_NUMBER,
                    PAID_AT,AUDIENCE,CHILD_CODE,PHOTO_PUBLIC_ID,PHOTO_CODE,PRODUCT_PUBLIC_ID,PRODUCT_NAME,PRODUCT_FORMAT,PRINT_COUNT,QUANTITY,PRINTS,ENTRY) VALUES '
                    . implode(',', $values));
                if (count($chunk) !== $connection->getAffectedRowsCount()) {
                    // A concurrent version already took one of these order lines: this snapshot is stale as a whole.
                    $connection->rollbackTransaction();

                    return false;
                }
            }
            $connection->commitTransaction();

            return true;
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();

            throw new ProductionStorageException('Cannot persist the print archive version.', 0, $error);
        }
    }

    public function find(string $publicId): ?ArchiveVersion
    {
        return $this->one('PUBLIC_ID=' . $this->text($publicId));
    }

    public function findByLinkHash(string $linkHash): ?ArchiveVersion
    {
        return 1 === preg_match('/^[a-f0-9]{64}$/D', $linkHash) ? $this->one("LINK_HASH='{$linkHash}'") : null;
    }

    public function byGroups(array $groupIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $groupIds), static fn(int $id): bool => 0 < $id));
        if ([] === $ids) {
            return [];
        }
        $result = $this->query('SELECT ' . self::COLUMNS . ' FROM mf_production_version WHERE GROUP_ID IN (' . implode(',', $ids) . ')
            ORDER BY GROUP_ID,DEADLINE_AT,VERSION_NO');
        $versions = [];
        while (false !== ($row = $result->fetch())) {
            /** @var VersionRow $row */
            $versions[(int)$row['GROUP_ID']][] = $this->version($row);
        }

        return $versions;
    }

    public function includedLines(int $groupId, \DateTimeImmutable $deadline): array
    {
        $result = $this->query("SELECT ORDER_LINE_ID FROM mf_production_line WHERE GROUP_ID={$groupId} AND DEADLINE_AT=" . $this->moment($deadline));
        $lines = [];
        while (false !== ($row = $result->fetch())) {
            $lines[] = (string)$row['ORDER_LINE_ID'];
        }

        return $lines;
    }

    public function nextNumber(int $groupId, \DateTimeImmutable $deadline): int
    {
        $row = $this->query("SELECT COALESCE(MAX(VERSION_NO),0)+1 AS NEXT_NO FROM mf_production_version WHERE GROUP_ID={$groupId} AND DEADLINE_AT="
            . $this->moment($deadline))->fetch();

        return false === $row ? 1 : (int)$row['NEXT_NO'];
    }

    public function lines(int $versionId): array
    {
        $result = $this->query("SELECT ORDER_LINE_ID,ORDER_PUBLIC_ID,ORDER_NUMBER,DATE_FORMAT(PAID_AT,'%Y-%m-%d %H:%i:%s') AS PAID_AT,AUDIENCE,CHILD_CODE,
            PHOTO_PUBLIC_ID,PHOTO_CODE,PRODUCT_PUBLIC_ID,PRODUCT_NAME,PRODUCT_FORMAT,PRINT_COUNT,QUANTITY,PRINTS,ENTRY
            FROM mf_production_line WHERE VERSION_ID={$versionId} ORDER BY ENTRY,ORDER_NUMBER,ID");
        $lines = [];
        while (false !== ($row = $result->fetch())) {
            $lines[] = new ArchiveLine(
                orderLineId: (string)$row['ORDER_LINE_ID'],
                orderId: (string)$row['ORDER_PUBLIC_ID'],
                orderNumber: (string)$row['ORDER_NUMBER'],
                paidAt: (string)$row['PAID_AT'],
                audience: (string)$row['AUDIENCE'],
                childCode: (string)$row['CHILD_CODE'],
                photoId: (string)$row['PHOTO_PUBLIC_ID'],
                photoCode: (string)$row['PHOTO_CODE'],
                productId: (string)$row['PRODUCT_PUBLIC_ID'],
                productName: (string)$row['PRODUCT_NAME'],
                productFormat: (string)$row['PRODUCT_FORMAT'],
                printCount: (int)$row['PRINT_COUNT'],
                quantity: (int)$row['QUANTITY'],
                prints: (int)$row['PRINTS'],
                entry: (string)$row['ENTRY'],
            );
        }

        return $lines;
    }

    public function products(array $versionIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $versionIds), static fn(int $id): bool => 0 < $id));
        if ([] === $ids) {
            return [];
        }
        $result = $this->query('SELECT VERSION_ID,PRODUCT_PUBLIC_ID,MIN(PRODUCT_NAME) AS PRODUCT_NAME,MIN(PRODUCT_FORMAT) AS PRODUCT_FORMAT,
            COUNT(DISTINCT ENTRY) AS FILES,SUM(PRINTS) AS PRINTS FROM mf_production_line WHERE VERSION_ID IN (' . implode(',', $ids) . ')
            GROUP BY VERSION_ID,PRODUCT_PUBLIC_ID ORDER BY VERSION_ID,PRODUCT_NAME,PRODUCT_PUBLIC_ID');
        $products = [];
        while (false !== ($row = $result->fetch())) {
            $products[(int)$row['VERSION_ID']][] = new ProductSummary(
                productId: (string)$row['PRODUCT_PUBLIC_ID'],
                name: (string)$row['PRODUCT_NAME'],
                format: (string)$row['PRODUCT_FORMAT'],
                files: (int)$row['FILES'],
                prints: (int)$row['PRINTS'],
            );
        }

        return $products;
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        return $this->many("STATUS='pending' AND (LEASE_UNTIL IS NULL OR LEASE_UNTIL<=" . $this->moment($now) . ") ORDER BY CREATED_AT,ID LIMIT {$limit}");
    }

    public function claim(string $publicId, int $maxAttempts, \DateTimeImmutable $now, \DateTimeImmutable $leaseUntil): bool
    {
        return 1 === $this->execute('UPDATE mf_production_version SET ATTEMPTS=ATTEMPTS+1,LEASE_UNTIL=' . $this->moment($leaseUntil)
            . ',UPDATED_AT=UTC_TIMESTAMP() WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='pending' AND ATTEMPTS<{$maxAttempts}"
            . ' AND (LEASE_UNTIL IS NULL OR LEASE_UNTIL<=' . $this->moment($now) . ')');
    }

    public function markReady(string $publicId, int $files, int $bytes, string $linkHash, \DateTimeImmutable $readyAt, \DateTimeImmutable $expiresAt): void
    {
        $this->execute("UPDATE mf_production_version SET STATUS='ready',FILES_COUNT={$files},BYTES={$bytes},LINK_HASH=" . $this->text($linkHash)
            . ',ERROR_CODE=NULL,LEASE_UNTIL=NULL,READY_AT=' . $this->moment($readyAt) . ',EXPIRES_AT=' . $this->moment($expiresAt)
            . ',UPDATED_AT=UTC_TIMESTAMP() WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='pending'");
    }

    public function markRetry(string $publicId, \DateTimeImmutable $retryAt): void
    {
        $this->execute('UPDATE mf_production_version SET LEASE_UNTIL=' . $this->moment($retryAt) . ',UPDATED_AT=UTC_TIMESTAMP()
            WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='pending'");
    }

    public function markFailed(string $publicId, string $errorCode): void
    {
        $this->execute("UPDATE mf_production_version SET STATUS='failed',ERROR_CODE=" . $this->text($errorCode) . ',LEASE_UNTIL=NULL,
            UPDATED_AT=UTC_TIMESTAMP() WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='pending'");
    }

    public function expired(\DateTimeImmutable $now, int $limit): array
    {
        return $this->many("STATUS='ready' AND EXPIRES_AT<=" . $this->moment($now) . " ORDER BY EXPIRES_AT,ID LIMIT {$limit}");
    }

    public function markExpired(string $publicId): void
    {
        $this->execute("UPDATE mf_production_version SET STATUS='expired',LINK_HASH=NULL,UPDATED_AT=UTC_TIMESTAMP() WHERE PUBLIC_ID="
            . $this->text($publicId) . " AND STATUS='ready'");
    }

    public function renew(string $publicId, int $expectedGeneration, ?string $linkHash): bool
    {
        $changes = null === $linkHash
            ? "STATUS='pending',ATTEMPTS=0,LEASE_UNTIL=NULL,ERROR_CODE=NULL,LINK_HASH=NULL,BYTES=NULL,READY_AT=NULL,EXPIRES_AT=NULL"
            : 'LINK_HASH=' . $this->text($linkHash);
        $allowed = null === $linkHash ? "STATUS IN ('expired','failed')" : "STATUS='ready'";

        return 1 === $this->execute("UPDATE mf_production_version SET {$changes},LINK_GENERATION=LINK_GENERATION+1,UPDATED_AT=UTC_TIMESTAMP()
            WHERE PUBLIC_ID=" . $this->text($publicId) . " AND LINK_GENERATION={$expectedGeneration} AND {$allowed}");
    }

    public function idempotency(int $actorId, string $keyHash): ?array
    {
        /** @var array{REQUEST_HASH: string, VERSION_ID: string}|false $row */
        $row = $this->query("SELECT r.REQUEST_HASH,v.PUBLIC_ID AS VERSION_ID FROM mf_production_request r INNER JOIN mf_production_version v ON v.ID=r.VERSION_ID
            WHERE r.ACTOR_ID={$actorId} AND r.KEY_HASH=" . $this->text($keyHash))->fetch();

        return false === $row ? null : ['request' => $row['REQUEST_HASH'], 'versionId' => $row['VERSION_ID']];
    }

    public function remember(int $actorId, string $keyHash, string $requestHash, string $versionId): void
    {
        $this->execute('INSERT IGNORE INTO mf_production_request(ACTOR_ID,KEY_HASH,REQUEST_HASH,VERSION_ID,CREATED_AT) SELECT ' . $actorId . ','
            . $this->text($keyHash) . ',' . $this->text($requestHash) . ',ID,UTC_TIMESTAMP() FROM mf_production_version WHERE PUBLIC_ID=' . $this->text($versionId));
    }

    private function one(string $where): ?ArchiveVersion
    {
        return $this->many($where . ' LIMIT 1')[0] ?? null;
    }

    /** @return list<ArchiveVersion> */
    private function many(string $where): array
    {
        $result = $this->query('SELECT ' . self::COLUMNS . ' FROM mf_production_version WHERE ' . $where);
        $versions = [];
        while (false !== ($row = $result->fetch())) {
            /** @var VersionRow $row */
            $versions[] = $this->version($row);
        }

        return $versions;
    }

    /** @param VersionRow $row */
    private function version(array $row): ArchiveVersion
    {
        return new ArchiveVersion(
            id: (int)$row['ID'],
            publicId: $row['PUBLIC_ID'],
            groupId: (int)$row['GROUP_ID'],
            groupPublicId: $row['GROUP_PUBLIC_ID'],
            institutionId: (int)$row['INSTITUTION_ID'],
            deadlineAt: $this->instant($row['DEADLINE_AT']),
            number: (int)$row['VERSION_NO'],
            status: VersionStatusEnum::from($row['STATUS']),
            linesCount: (int)$row['LINES_COUNT'],
            filesCount: (int)$row['FILES_COUNT'],
            printsCount: (int)$row['PRINTS_COUNT'],
            waitingOrders: (int)$row['WAITING_ORDERS'],
            archivePath: $row['ARCHIVE_PATH'],
            bytes: null === $row['BYTES'] ? null : (int)$row['BYTES'],
            errorCode: $row['ERROR_CODE'],
            attempts: (int)$row['ATTEMPTS'],
            linkGeneration: (int)$row['LINK_GENERATION'],
            createdAt: $this->instant($row['CREATED_AT']),
            readyAt: null === $row['READY_AT'] ? null : $this->instant($row['READY_AT']),
            expiresAt: null === $row['EXPIRES_AT'] ? null : $this->instant($row['EXPIRES_AT']),
            createdBy: null === $row['CREATED_BY'] ? null : (int)$row['CREATED_BY'],
        );
    }

    private function instant(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private function moment(\DateTimeImmutable $value): string
    {
        return "'" . $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') . "'";
    }

    private function text(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new ProductionStorageException('Cannot read print archives.', 0, $error);
        }
    }

    private function execute(string $sql): int
    {
        try {
            $connection = Application::getConnection();
            $connection->queryExecute($sql);

            return $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new ProductionStorageException('Cannot change print archives.', 0, $error);
        }
    }
}
