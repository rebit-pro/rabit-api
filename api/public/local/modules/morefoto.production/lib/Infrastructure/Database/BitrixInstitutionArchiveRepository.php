<?php

declare(strict_types=1);

namespace Morefoto\Production\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Institution\Exception\InstitutionArchiveBusyException;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveGroup;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveRequest;
use Morefoto\Production\Domain\Institution\ValueObject\NewInstitutionArchive;

/** Хранит артефакты доставки и ссылки на задания L1; mutex принадлежит соединению, аренда сборки — конкретному owner. */
final readonly class BitrixInstitutionArchiveRepository implements InstitutionArchiveRepositoryInterface
{
    private const string SELECT = "SELECT *,DATE_FORMAT(CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_UTC,
        DATE_FORMAT(READY_AT,'%Y-%m-%d %H:%i:%s') AS READY_UTC,DATE_FORMAT(EXPIRES_AT,'%Y-%m-%d %H:%i:%s') AS EXPIRES_UTC,
        DATE_FORMAT(FIRST_READY_AT,'%Y-%m-%d %H:%i:%s') AS FIRST_READY_UTC
        FROM mf_production_institution_archive";

    public function cursor(): int
    {
        $row = $this->query('SELECT INSTITUTION_CURSOR_ID FROM mf_production_schedule WHERE ID=1')->fetch();
        if (false === $row) {
            throw new ProductionStorageException('Production schedule is missing.');
        }

        return (int)$row['INSTITUTION_CURSOR_ID'];
    }

    public function advance(int $id): void
    {
        $this->execute('UPDATE mf_production_schedule SET INSTITUTION_CURSOR_ID=' . max(0, $id) . ' WHERE ID=1');
    }

    public function exclusive(string $key, \Closure $operation): mixed
    {
        $name = $this->text('mf.iarch.' . substr(hash('sha256', $key), 0, 54));
        $row = $this->query('SELECT GET_LOCK(' . $name . ',0) AS ACQUIRED')->fetch();
        if (false === $row || '1' !== (string)$row['ACQUIRED']) {
            throw new InstitutionArchiveBusyException('Institution archive operation is already running.');
        }
        try {
            return $operation();
        } finally {
            $this->query('SELECT RELEASE_LOCK(' . $name . ')');
        }
    }

    public function create(NewInstitutionArchive $archive, ?InstitutionArchiveRequest $request = null): InstitutionArchive
    {
        return $this->atomic(function() use ($archive, $request): InstitutionArchive {
            $saved = $this->insert($archive);
            if (null !== $request) {
                $this->remember($request, $saved->publicId);
            }

            return $saved;
        });
    }

    private function insert(NewInstitutionArchive $archive): InstitutionArchive
    {
        $where = 'INSTITUTION_ID=' . $archive->institutionId . ' AND KIND=' . $this->text($archive->kind) . ' AND FINGERPRINT=' . $this->text($archive->fingerprint);
        $existing = $this->many($where . ' LIMIT 1')[0] ?? null;
        if (null !== $existing) {
            return $existing;
        }
        $row = $this->query('SELECT COALESCE(MAX(VERSION_NO),0)+1 AS NEXT_NO FROM mf_production_institution_archive WHERE INSTITUTION_ID=' . $archive->institutionId)->fetch();
        $number = false === $row ? 1 : (int)$row['NEXT_NO'];
        $snapshot = json_encode(array_map(static fn(InstitutionArchiveGroup $group): array => [
            'id' => $group->id, 'name' => $group->name, 'deadline' => $group->deadline,
            'sources' => $group->sourceVersionIds, 'entries' => $group->entries,
        ], $archive->groups), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->execute('INSERT INTO mf_production_institution_archive(PUBLIC_ID,INSTITUTION_ID,INSTITUTION_PUBLIC_ID,VERSION_NO,KIND,FINGERPRINT,SNAPSHOT,
            LINES_COUNT,FILES_COUNT,PRINTS_COUNT,STATUS,CREATED_AT) VALUES(' . implode(',', [
            $this->text($archive->publicId), $archive->institutionId, $this->text($archive->institutionPublicId), $number,
            $this->text($archive->kind), $this->text($archive->fingerprint), $this->text($snapshot),
            $archive->lines, $archive->files, $archive->prints, $this->text(0 === $archive->lines ? 'empty' : 'pending'), $this->moment($archive->createdAt),
        ]) . ')');

        return $this->find($archive->publicId) ?? throw new ProductionStorageException('Institution archive was not persisted.');
    }

    public function find(string $id): ?InstitutionArchive
    {
        return $this->many('PUBLIC_ID=' . $this->text($id) . ' LIMIT 1')[0] ?? null;
    }

    public function history(int $institutionId): array
    {
        return $this->many('INSTITUTION_ID=' . $institutionId . ' ORDER BY VERSION_NO');
    }

    public function planningError(int $institutionId): ?string
    {
        $row = $this->query('SELECT ERROR_CODE FROM mf_production_institution_state WHERE INSTITUTION_ID=' . $institutionId)->fetch();

        return false === $row || null === $row['ERROR_CODE'] ? null : (string)$row['ERROR_CODE'];
    }

    public function recordPlanningError(int $institutionId, ?string $error): void
    {
        $this->execute('INSERT INTO mf_production_institution_state(INSTITUTION_ID,ERROR_CODE) VALUES(' . $institutionId . ',' . $this->nullable($error)
            . ') ON DUPLICATE KEY UPDATE ERROR_CODE=VALUES(ERROR_CODE)');
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        return $this->many("STATUS='pending' AND (LEASE_UNTIL IS NULL OR LEASE_UNTIL<=" . $this->moment($now) . ') ORDER BY ID LIMIT ' . max(1, min(200, $limit)));
    }

    public function claim(string $id, string $owner, string $path, \DateTimeImmutable $now, \DateTimeImmutable $until): bool
    {
        // Захват сверх бюджета завершает выпуск в failed; его тоже можно повторить после аварии.
        return 1 === $this->execute('UPDATE mf_production_institution_archive SET ATTEMPTS=LEAST(ATTEMPTS+1,' . (ArchivePolicy::MAX_ATTEMPTS + 1) . '),LEASE_OWNER=' . $this->text($owner)
            . ',WORK_PATH=' . $this->text($path) . ',LEASE_UNTIL=' . $this->moment($until) . ' WHERE PUBLIC_ID=' . $this->text($id)
            . " AND STATUS='pending' AND (LEASE_UNTIL IS NULL OR LEASE_UNTIL<=" . $this->moment($now) . ')');
    }

    public function ready(string $id, string $owner, int $bytes, \DateTimeImmutable $now, \DateTimeImmutable $expires): bool
    {
        return 1 === $this->execute("UPDATE mf_production_institution_archive SET STATUS='ready',ARCHIVE_PATH=WORK_PATH,WORK_PATH=NULL,OBSOLETE_PATH=NULL,BYTES=" . $bytes
            . ',READY_AT=' . $this->moment($now) . ',FIRST_READY_AT=COALESCE(FIRST_READY_AT,' . $this->moment($now) . '),EXPIRES_AT=' . $this->moment($expires) . ',LEASE_UNTIL=NULL,LEASE_OWNER=NULL,ERROR_CODE=NULL WHERE PUBLIC_ID='
            . $this->text($id) . " AND STATUS='pending' AND LEASE_OWNER=" . $this->text($owner) . ' AND LEASE_UNTIL>' . $this->moment($now));
    }

    public function failed(string $id, string $owner, string $error, ?\DateTimeImmutable $retryAt): bool
    {
        return 1 === $this->execute('UPDATE mf_production_institution_archive SET STATUS=' . $this->text(null === $retryAt ? 'failed' : 'pending')
            . ',ERROR_CODE=' . $this->text($error) . ',LEASE_OWNER=NULL,LEASE_UNTIL=' . (null === $retryAt ? 'NULL' : $this->moment($retryAt))
            . ',WORK_PATH=NULL WHERE PUBLIC_ID=' . $this->text($id) . " AND STATUS='pending' AND LEASE_OWNER=" . $this->text($owner));
    }

    public function expired(\DateTimeImmutable $now, int $limit): array
    {
        return $this->many("STATUS='ready' AND EXPIRES_AT<=" . $this->moment($now) . ' ORDER BY ID LIMIT ' . max(1, min(200, $limit)));
    }

    public function expire(string $id, int $generation): void
    {
        $this->execute("UPDATE mf_production_institution_archive SET STATUS='expired' WHERE PUBLIC_ID=" . $this->text($id)
            . " AND STATUS='ready' AND GENERATION=" . $generation);
    }

    public function renew(string $id, int $generation, bool $rebuild, InstitutionArchiveRequest $request): bool
    {
        return $this->atomic(function() use ($id, $generation, $rebuild, $request): bool {
            if (!$this->updateGeneration($id, $generation, $rebuild)) {
                return false;
            }
            $this->remember($request, $id);

            return true;
        });
    }

    private function updateGeneration(string $id, int $generation, bool $rebuild): bool
    {
        $changes = $rebuild ? ",STATUS='pending',ATTEMPTS=0,ERROR_CODE=NULL,LEASE_OWNER=NULL,LEASE_UNTIL=NULL,WORK_PATH=NULL,OBSOLETE_PATH=COALESCE(ARCHIVE_PATH,OBSOLETE_PATH),ARCHIVE_PATH=NULL,BYTES=NULL,READY_AT=NULL,EXPIRES_AT=NULL" : '';

        return 1 === $this->execute('UPDATE mf_production_institution_archive SET GENERATION=GENERATION+1' . $changes . ' WHERE PUBLIC_ID=' . $this->text($id)
            . ' AND GENERATION=' . $generation . " AND STATUS IN ('ready','failed','expired')");
    }

    public function request(int $actorId, string $keyHash): ?array
    {
        $row = $this->query('SELECT REQUEST_HASH,ARCHIVE_PUBLIC_ID FROM mf_production_institution_request WHERE ACTOR_ID=' . $actorId . ' AND KEY_HASH=' . $this->text($keyHash))->fetch();

        return false === $row ? null : ['request' => (string)$row['REQUEST_HASH'], 'archiveId' => (string)$row['ARCHIVE_PUBLIC_ID']];
    }

    private function remember(InstitutionArchiveRequest $request, string $archiveId): void
    {
        if (1 !== $this->execute('INSERT INTO mf_production_institution_request(ACTOR_ID,KEY_HASH,REQUEST_HASH,ARCHIVE_PUBLIC_ID) VALUES('
            . $request->actorId . ',' . $this->text($request->keyHash) . ',' . $this->text($request->requestHash) . ',' . $this->text($archiveId) . ')')) {
            throw new ProductionStorageException('Cannot persist institution request.');
        }
    }

    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    private function atomic(\Closure $operation): mixed
    {
        $db = Application::getConnection();
        $started = false;
        try {
            $db->startTransaction();
            $started = true;
            $result = $operation();
            $db->commitTransaction();

            return $result;
        } catch (\Throwable $error) {
            if ($started) {
                $db->rollbackTransaction();
            }
            throw new ProductionStorageException('Cannot complete institution archive command.', 0, $error);
        }
    }

    /** @return list<InstitutionArchive> */
    private function many(string $where): array
    {
        $result = $this->query(self::SELECT . ' WHERE ' . $where);
        $items = [];
        while (false !== ($row = $result->fetch())) {
            $items[] = $this->item($row);
        }

        return $items;
    }

    /** @param array<string, mixed> $row */
    private function item(array $row): InstitutionArchive
    {
        /** @var list<array{
         *     id: string,
         *     name: string,
         *     deadline: string,
         *     sources: list<int>,
         *     entries: array<string, string>,
         * }> $snapshot */
        $snapshot = json_decode((string)$row['SNAPSHOT'], true, 512, JSON_THROW_ON_ERROR);
        $groups = array_map(static fn(array $group): InstitutionArchiveGroup => new InstitutionArchiveGroup(
            id: $group['id'],
            name: $group['name'],
            deadline: $group['deadline'],
            sourceVersionIds: $group['sources'],
            entries: $group['entries'],
        ), $snapshot);

        return new InstitutionArchive(
            publicId: (string)$row['PUBLIC_ID'],
            institutionId: (int)$row['INSTITUTION_ID'],
            institutionPublicId: (string)$row['INSTITUTION_PUBLIC_ID'],
            number: (int)$row['VERSION_NO'],
            kind: (string)$row['KIND'],
            fingerprint: (string)$row['FINGERPRINT'],
            groups: $groups,
            lines: (int)$row['LINES_COUNT'],
            files: (int)$row['FILES_COUNT'],
            prints: (int)$row['PRINTS_COUNT'],
            status: VersionStatusEnum::from((string)$row['STATUS']),
            error: null === $row['ERROR_CODE'] ? null : (string)$row['ERROR_CODE'],
            attempts: (int)$row['ATTEMPTS'],
            generation: (int)$row['GENERATION'],
            archivePath: null === $row['ARCHIVE_PATH'] ? null : (string)$row['ARCHIVE_PATH'],
            workPath: null === $row['WORK_PATH'] ? null : (string)$row['WORK_PATH'],
            obsoletePath: null === $row['OBSOLETE_PATH'] ? null : (string)$row['OBSOLETE_PATH'],
            bytes: null === $row['BYTES'] ? null : (int)$row['BYTES'],
            createdAt: new \DateTimeImmutable((string)$row['CREATED_UTC'], new \DateTimeZone('UTC')),
            readyAt: null === $row['READY_UTC'] ? null : new \DateTimeImmutable((string)$row['READY_UTC'], new \DateTimeZone('UTC')),
            firstReadyAt: null === $row['FIRST_READY_UTC'] ? null : new \DateTimeImmutable((string)$row['FIRST_READY_UTC'], new \DateTimeZone('UTC')),
            expiresAt: null === $row['EXPIRES_UTC'] ? null : new \DateTimeImmutable((string)$row['EXPIRES_UTC'], new \DateTimeZone('UTC')),
        );
    }

    private function nullable(?string $value): string
    {
        return null === $value ? 'NULL' : $this->text($value);
    }

    private function text(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    private function moment(\DateTimeImmutable $value): string
    {
        return $this->text($value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new ProductionStorageException('Cannot read institution archives.', 0, $error);
        }
    }

    private function execute(string $sql): int
    {
        try {
            $db = Application::getConnection();
            $db->queryExecute($sql);

            return $db->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new ProductionStorageException('Cannot persist institution archive.', 0, $error);
        }
    }
}
