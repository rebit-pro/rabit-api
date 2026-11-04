<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Handoff\Domain\Link\Enum\OpeningStatusEnum;
use Morefoto\Handoff\Domain\Link\Repository\OpeningScheduleRepositoryInterface;
use Morefoto\Handoff\Domain\Link\ValueObject\OpeningSchedule;
use Morefoto\Handoff\Domain\Link\Exception\LinkStorageException;
use Bitrix\Main\DB\Result;
use Bitrix\Main\Type\DateTime;

final readonly class BitrixOpeningScheduleRepository implements OpeningScheduleRepositoryInterface
{
    public function forGroups(array $groupIds): array
    {
        if ([] === $groupIds) {
            return [];
        }
        $ids = implode(',', array_map($this->id(...), array_values(array_unique($groupIds))));
        $result = $this->query("SELECT GROUP_ID,OPEN_AT,STATUS,SCHEDULED_BY,OPENED_AT,MANUAL_SENT_AT,MANUAL_SENT_BY FROM mf_gallery_opening WHERE GROUP_ID IN ({$ids})");
        $schedules = [];
        while (false !== ($row = $result->fetch())) {
            $schedule = $this->hydrate($row);
            $schedules[$schedule->groupId] = $schedule;
        }

        return $schedules;
    }

    public function find(int $groupId, bool $forUpdate = false): ?OpeningSchedule
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $row = $this->query('SELECT GROUP_ID,OPEN_AT,STATUS,SCHEDULED_BY,OPENED_AT,MANUAL_SENT_AT,MANUAL_SENT_BY FROM mf_gallery_opening WHERE GROUP_ID=' . $this->id($groupId) . $lock)->fetch();

        return false === $row ? null : $this->hydrate($row);
    }

    public function findByPublicId(string $groupId): ?OpeningSchedule
    {
        if (1 !== preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $groupId)) {
            throw new \InvalidArgumentException('Invalid group ID.');
        }
        $row = $this->query("SELECT o.GROUP_ID,o.OPEN_AT,o.STATUS,o.SCHEDULED_BY,o.OPENED_AT,o.MANUAL_SENT_AT,o.MANUAL_SENT_BY
            FROM mf_gallery_opening o JOIN b_hlbd_mf_group g ON g.ID=o.GROUP_ID WHERE g.UF_PUBLIC_ID='{$groupId}'")->fetch();

        return false === $row ? null : $this->hydrate($row);
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        if (1 > $limit || 200 < $limit) {
            throw new \InvalidArgumentException('Invalid opening batch limit.');
        }
        $until = $this->moment($now);
        $result = $this->query("SELECT g.UF_PUBLIC_ID FROM mf_gallery_opening o JOIN b_hlbd_mf_group g ON g.ID=o.GROUP_ID
            WHERE o.STATUS='scheduled' AND o.OPEN_AT<='{$until}' ORDER BY o.OPEN_AT,o.GROUP_ID LIMIT {$limit}");
        $ids = [];
        while (false !== ($row = $result->fetch())) {
            $ids[] = (string)$row['UF_PUBLIC_ID'];
        }

        return $ids;
    }

    public function schedule(int $groupId, \DateTimeImmutable $openAt, int $actorId): void
    {
        $id = $this->id($groupId);
        $actor = $this->id($actorId);
        $at = $this->moment($openAt);
        $this->execute("INSERT INTO mf_gallery_opening(GROUP_ID,OPEN_AT,STATUS,SCHEDULED_BY,CREATED_AT,UPDATED_AT)
            VALUES({$id},'{$at}','scheduled',{$actor},UTC_TIMESTAMP(),UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE OPEN_AT=VALUES(OPEN_AT),STATUS='scheduled',SCHEDULED_BY=VALUES(SCHEDULED_BY),OPENED_AT=NULL,UPDATED_AT=UTC_TIMESTAMP()");
    }

    public function cancel(int $groupId): void
    {
        $this->execute("UPDATE mf_gallery_opening SET STATUS='cancelled',OPENED_AT=NULL,UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID=" . $this->id($groupId));
    }

    public function block(int $groupId): void
    {
        $this->execute("UPDATE mf_gallery_opening SET STATUS='blocked',OPENED_AT=NULL,UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID=" . $this->id($groupId));
    }

    public function opened(int $groupId, \DateTimeImmutable $now): void
    {
        $this->execute("UPDATE mf_gallery_opening SET STATUS='opened',OPENED_AT='" . $this->moment($now) . "',UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID=" . $this->id($groupId));
    }

    public function recordManual(int $groupId, \DateTimeImmutable $sentAt, int $actorId): void
    {
        $this->execute("UPDATE mf_gallery_opening SET MANUAL_SENT_AT='" . $this->moment($sentAt) . "',MANUAL_SENT_BY=" . $this->id($actorId)
            . ',UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID=' . $this->id($groupId) . ' AND MANUAL_SENT_AT IS NULL');
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new \LogicException('Manual link delivery was already recorded.');
        }
    }

    /** @param array{GROUP_ID: int|string, OPEN_AT: DateTime|string, STATUS: string, SCHEDULED_BY: int|string, OPENED_AT: null|DateTime|string, MANUAL_SENT_AT: null|DateTime|string, MANUAL_SENT_BY: null|int|string} $row */
    private function hydrate(array $row): OpeningSchedule
    {
        return new OpeningSchedule(
            groupId: (int)$row['GROUP_ID'],
            openAt: $this->stored($row['OPEN_AT']) ?? throw new \UnexpectedValueException('Opening moment is missing.'),
            status: OpeningStatusEnum::from($row['STATUS']),
            scheduledBy: (int)$row['SCHEDULED_BY'],
            openedAt: $this->stored($row['OPENED_AT']),
            manualSentAt: $this->stored($row['MANUAL_SENT_AT']),
            manualSentBy: null === $row['MANUAL_SENT_BY'] ? null : (int)$row['MANUAL_SENT_BY'],
        );
    }

    private function stored(DateTime|string|null $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof DateTime) {
            // Bitrix wraps a raw DATETIME in its display timezone; the column stores UTC wall time.
            $value = $value->format('Y-m-d H:i:s');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new \UnexpectedValueException('Invalid stored opening moment.');
        }

        return $date;
    }

    private function moment(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function id(int $value): int
    {
        if (1 > $value) {
            throw new \InvalidArgumentException('A positive identifier is required.');
        }

        return $value;
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new LinkStorageException('Cannot read gallery opening.', 0, $error);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $error) {
            throw new LinkStorageException('Cannot save gallery opening.', 0, $error);
        }
    }
}
