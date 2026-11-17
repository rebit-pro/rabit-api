<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;
use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Morefoto\Files\Domain\Institution\Service\DigitalArchivePolicy;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;

/**
 * Хранит собственные снимки Files и отделяет операции учреждения, повтор запроса и сборку scoped-блокировками MySQL.
 * Каждый файловый путь сохраняется до записи ZIP, готовность публикуется CAS по владельцу попытки.
 *
 * @phpstan-type ArchiveRow array{
 *     PUBLIC_ID: string, INSTITUTION_ID: int|string, INSTITUTION_PUBLIC_ID: string, ACTOR_ID: int|string,
 *     KEY_HASH: string, CAPTURED_AT: string, SNAPSHOT: string, STATUS: string, ATTEMPTS: int|string,
 *     LEASE_OWNER: null|string, WORK_PATHS: string, ARCHIVE_PATH: null|string, BYTES: null|int|string,
 *     ERROR_CODE: null|string, READY_AT: null|string, EXPIRES_AT: null|string,
 * }
 */
final readonly class BitrixDigitalArchiveRepository implements DigitalArchiveRepositoryInterface
{
    private const string COLUMNS = "PUBLIC_ID,INSTITUTION_ID,INSTITUTION_PUBLIC_ID,ACTOR_ID,KEY_HASH,SNAPSHOT,STATUS,ATTEMPTS,
        LEASE_OWNER,WORK_PATHS,ARCHIVE_PATH,BYTES,ERROR_CODE,
        DATE_FORMAT(CAPTURED_AT,'%Y-%m-%d %H:%i:%s') AS CAPTURED_AT,
        DATE_FORMAT(READY_AT,'%Y-%m-%d %H:%i:%s') AS READY_AT,DATE_FORMAT(EXPIRES_AT,'%Y-%m-%d %H:%i:%s') AS EXPIRES_AT";

    public function __construct(private DigitalArchiveCodec $codec = new DigitalArchiveCodec()) {}

    public function exclusive(string $scope, \Closure $operation): mixed
    {
        $connection = Application::getConnection();
        $name = $this->text('mf.digital.' . substr(hash('sha256', $scope), 0, 48));
        try {
            $row = $connection->query('SELECT GET_LOCK(' . $name . ',0) AS ACQUIRED')->fetch();
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot lock digital archive.', 0, $error);
        }
        if (false === $row || 1 !== (int)$row['ACQUIRED']) {
            throw new DigitalArchiveException('OPERATION_IN_PROGRESS');
        }
        try {
            return $operation();
        } finally {
            $connection->query('SELECT RELEASE_LOCK(' . $name . ')');
        }
    }

    public function find(string $id): ?DigitalArchive
    {
        return $this->many('PUBLIC_ID=' . $this->text($id))[0] ?? null;
    }

    public function request(int $actorId, string $keyHash): ?DigitalArchive
    {
        return $this->many('ACTOR_ID=' . $actorId . ' AND KEY_HASH=' . $this->text($keyHash))[0] ?? null;
    }

    public function latest(int $institutionId): array
    {
        try {
            $result = Application::getConnection()->query("SELECT PUBLIC_ID,STATUS,JSON_LENGTH(SNAPSHOT) AS FILES_COUNT,ATTEMPTS,BYTES,ERROR_CODE,
                DATE_FORMAT(CAPTURED_AT,'%Y-%m-%d %H:%i:%s') AS CAPTURED_AT,DATE_FORMAT(READY_AT,'%Y-%m-%d %H:%i:%s') AS READY_AT,
                DATE_FORMAT(EXPIRES_AT,'%Y-%m-%d %H:%i:%s') AS EXPIRES_AT
                FROM mf_institution_digital_archive WHERE INSTITUTION_ID={$institutionId} ORDER BY ID DESC LIMIT 20");
            $items = [];
            while (false !== ($row = $result->fetch())) {
                /** @var array{PUBLIC_ID: string, STATUS: string, FILES_COUNT: int|string, ATTEMPTS: int|string, BYTES: null|int|string, ERROR_CODE: null|string, CAPTURED_AT: string, READY_AT: null|string, EXPIRES_AT: null|string} $row */
                $items[] = [
                    'id' => $row['PUBLIC_ID'], 'status' => $row['STATUS'], 'files' => (int)$row['FILES_COUNT'], 'attempts' => (int)$row['ATTEMPTS'],
                    'bytes' => null === $row['BYTES'] ? null : (int)$row['BYTES'], 'error' => $row['ERROR_CODE'],
                    'capturedAt' => new \DateTimeImmutable($row['CAPTURED_AT'], new \DateTimeZone('UTC')),
                    'readyAt' => $this->date($row['READY_AT']), 'expiresAt' => $this->date($row['EXPIRES_AT']),
                ];
            }

            return $items;
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot read digital archive summaries.', 0, $error);
        }
    }

    public function pending(int $institutionId): ?DigitalArchive
    {
        return $this->many("INSTITUTION_ID={$institutionId} AND STATUS='pending' ORDER BY ID LIMIT 1")[0] ?? null;
    }

    public function insert(DigitalArchive $archive): void
    {
        $values = [
            $this->text($archive->id), $archive->institutionId, $this->text($archive->institutionPublicId),
            $archive->actorId, $this->text($archive->keyHash), $this->moment($archive->capturedAt),
            $this->text($this->codec->encode($archive->files)), $this->text($archive->status),
            $this->moment($archive->capturedAt), "'[]'",
        ];
        if (1 !== $this->execute('INSERT INTO mf_institution_digital_archive(PUBLIC_ID,INSTITUTION_ID,INSTITUTION_PUBLIC_ID,ACTOR_ID,KEY_HASH,
            CAPTURED_AT,SNAPSHOT,STATUS,NEXT_AT,WORK_PATHS) VALUES(' . implode(',', $values) . ')')) {
            throw new FilesStorageException('Cannot persist digital archive.');
        }
    }

    public function claim(string $id, string $owner, string $path, \DateTimeImmutable $now, \DateTimeImmutable $until): bool
    {
        return 1 === $this->execute('UPDATE mf_institution_digital_archive SET ATTEMPTS=ATTEMPTS+1,LEASE_OWNER=' . $this->text($owner)
            . ',NEXT_AT=' . $this->moment($until) . ',WORK_PATHS=JSON_ARRAY_APPEND(WORK_PATHS,\'$\',' . $this->text($path) . ')'
            . ' WHERE PUBLIC_ID=' . $this->text($id) . " AND STATUS='pending' AND ATTEMPTS<" . DigitalArchivePolicy::MAX_ATTEMPTS
            . ' AND NEXT_AT<=' . $this->moment($now));
    }

    public function ready(string $id, string $owner, string $path, int $bytes, \DateTimeImmutable $at, \DateTimeImmutable $expires): bool
    {
        return 1 === $this->execute("UPDATE mf_institution_digital_archive SET STATUS='ready',NEXT_AT=NULL,ERROR_CODE=NULL,ARCHIVE_PATH=" . $this->text($path)
            . ',BYTES=' . $bytes . ',READY_AT=' . $this->moment($at) . ',EXPIRES_AT=' . $this->moment($expires)
            . ' WHERE PUBLIC_ID=' . $this->text($id) . " AND STATUS='pending' AND LEASE_OWNER=" . $this->text($owner));
    }

    public function failed(string $id, ?string $owner, string $error, \DateTimeImmutable $at): bool
    {
        $ownerCondition = null === $owner ? 'LEASE_OWNER IS NULL' : 'LEASE_OWNER=' . $this->text($owner);

        return 1 === $this->execute("UPDATE mf_institution_digital_archive SET STATUS='failed',NEXT_AT=NULL,ERROR_CODE=" . $this->text($error)
            . ',EXPIRES_AT=' . $this->moment($at) . ' WHERE PUBLIC_ID=' . $this->text($id) . " AND STATUS='pending' AND " . $ownerCondition);
    }

    public function retry(string $id, string $owner, \DateTimeImmutable $at): void
    {
        if (1 !== $this->execute('UPDATE mf_institution_digital_archive SET LEASE_OWNER=NULL,NEXT_AT=' . $this->moment($at)
            . ' WHERE PUBLIC_ID=' . $this->text($id) . " AND STATUS='pending' AND LEASE_OWNER=" . $this->text($owner))) {
            throw new FilesStorageException('Digital archive retry lost ownership.');
        }
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        try {
            $result = Application::getConnection()->query("SELECT PUBLIC_ID,ATTEMPTS FROM mf_institution_digital_archive WHERE STATUS='pending' AND NEXT_AT<="
                . $this->moment($now) . ' ORDER BY NEXT_AT,ID LIMIT ' . max(1, $limit));
            $items = [];
            while (false !== ($row = $result->fetch())) {
                $items[] = ['id' => (string)$row['PUBLIC_ID'], 'attempts' => (int)$row['ATTEMPTS']];
            }

            return $items;
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot read pending digital archive IDs.', 0, $error);
        }
    }

    public function stale(\DateTimeImmutable $now, int $limit): array
    {
        try {
            $result = Application::getConnection()->query("SELECT PUBLIC_ID FROM mf_institution_digital_archive WHERE STATUS IN ('ready','failed') AND EXPIRES_AT<=" . $this->moment($now)
                . ' AND (ARCHIVE_PATH IS NOT NULL OR JSON_LENGTH(WORK_PATHS)>0) ORDER BY EXPIRES_AT,ID LIMIT ' . max(1, $limit));
            $ids = [];
            while (false !== ($row = $result->fetch())) {
                $ids[] = (string)$row['PUBLIC_ID'];
            }

            return $ids;
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot read expired digital archive IDs.', 0, $error);
        }
    }

    public function purged(string $id, \DateTimeImmutable $now): void
    {
        if (1 !== $this->execute("UPDATE mf_institution_digital_archive SET STATUS=IF(STATUS='ready','expired',STATUS),ARCHIVE_PATH=NULL,WORK_PATHS='[]',LEASE_OWNER=NULL"
            . ' WHERE PUBLIC_ID=' . $this->text($id) . " AND STATUS IN ('ready','failed') AND EXPIRES_AT<=" . $this->moment($now))) {
            throw new FilesStorageException('Digital archive purge lost its state.');
        }
    }

    /** @return list<DigitalArchive> */
    private function many(string $where): array
    {
        try {
            $result = Application::getConnection()->query('SELECT ' . self::COLUMNS . ' FROM mf_institution_digital_archive WHERE ' . $where);
            $archives = [];
            while (false !== ($row = $result->fetch())) {
                /** @var ArchiveRow $row */
                /** @var list<string> $paths */
                $paths = json_decode($row['WORK_PATHS'], true, 2, JSON_THROW_ON_ERROR);
                $archives[] = new DigitalArchive(
                    id: $row['PUBLIC_ID'],
                    institutionId: (int)$row['INSTITUTION_ID'],
                    institutionPublicId: $row['INSTITUTION_PUBLIC_ID'],
                    actorId: (int)$row['ACTOR_ID'],
                    keyHash: $row['KEY_HASH'],
                    capturedAt: new \DateTimeImmutable($row['CAPTURED_AT'], new \DateTimeZone('UTC')),
                    files: $this->codec->decode($row['SNAPSHOT']),
                    status: $row['STATUS'],
                    attempts: (int)$row['ATTEMPTS'],
                    owner: $row['LEASE_OWNER'],
                    workPaths: $paths,
                    archivePath: $row['ARCHIVE_PATH'],
                    bytes: null === $row['BYTES'] ? null : (int)$row['BYTES'],
                    error: $row['ERROR_CODE'],
                    readyAt: $this->date($row['READY_AT']),
                    expiresAt: $this->date($row['EXPIRES_AT']),
                );
            }

            return $archives;
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot read digital archives.', 0, $error);
        }
    }

    private function execute(string $sql): int
    {
        try {
            $connection = Application::getConnection();
            $connection->queryExecute($sql);

            return $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot change digital archive.', 0, $error);
        }
    }

    private function text(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    private function moment(\DateTimeImmutable $value): string
    {
        return "'" . $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') . "'";
    }

    private function date(?string $value): ?\DateTimeImmutable
    {
        return null === $value ? null : new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}
