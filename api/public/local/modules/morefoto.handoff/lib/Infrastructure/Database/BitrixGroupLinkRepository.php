<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Handoff\Domain\Link\Exception\LinkStorageException;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkHistoryEntry;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class BitrixGroupLinkRepository implements GroupLinkRepositoryInterface
{
    private const string MOMENT = "DATE_FORMAT(%s,'%%Y-%%m-%%d %%H:%%i:%%s') AS %s";

    public function states(array $groupIds): array
    {
        $ids = $this->ids($groupIds);
        if ('' === $ids) {
            return [];
        }
        $result = $this->query('SELECT GROUP_ID,REVISION,PREPARED_SIGNATURE FROM mf_group_link WHERE GROUP_ID IN (' . $ids . ')');
        $states = [];
        while (false !== ($row = $result->fetch())) {
            $states[(int)$row['GROUP_ID']] = $this->state($row);
        }

        return $states;
    }

    public function preparedCount(array $groupIds): int
    {
        $ids = $this->ids($groupIds);
        if ('' === $ids) {
            return 0;
        }
        $row = $this->query('SELECT COUNT(*) AS PREPARED FROM mf_group_link WHERE PREPARED_SIGNATURE IS NOT NULL AND GROUP_ID IN (' . $ids . ')')->fetch();

        return false === $row ? 0 : (int)$row['PREPARED'];
    }

    public function lock(int $groupId): LinkState
    {
        $row = $this->query('SELECT REVISION,PREPARED_SIGNATURE FROM mf_group_link WHERE GROUP_ID=' . $this->id($groupId) . ' FOR UPDATE')->fetch();

        return false === $row ? new LinkState() : $this->state($row);
    }

    public function prepare(int $groupId, int $expectedRevision, string $signature, int $actorId): int
    {
        $signature = $this->quote($signature);
        if (1 === $expectedRevision) {
            $this->execute('INSERT INTO mf_group_link(GROUP_ID,REVISION,PREPARED_SIGNATURE,PREPARED_AT,PREPARED_BY,UPDATED_AT) VALUES('
                . $this->id($groupId) . ",2,{$signature},UTC_TIMESTAMP()," . $this->id($actorId) . ',UTC_TIMESTAMP())');

            return 2;
        }

        return $this->advanceWith($groupId, $expectedRevision, ",PREPARED_SIGNATURE={$signature},PREPARED_AT=UTC_TIMESTAMP(),PREPARED_BY=" . $this->id($actorId));
    }

    public function advance(int $groupId, int $expectedRevision): int
    {
        if (1 === $expectedRevision) {
            $this->execute('INSERT INTO mf_group_link(GROUP_ID,REVISION,UPDATED_AT) VALUES(' . $this->id($groupId) . ',2,UTC_TIMESTAMP())');

            return 2;
        }

        return $this->advanceWith($groupId, $expectedRevision, '');
    }

    public function appendHistory(LinkHistoryEntry $entry): void
    {
        $values = [
            $this->id($entry->groupId),
            $this->quote($entry->kind->value),
            $this->id($entry->actorId),
            $this->quote($entry->actorName),
            $this->nullable($entry->signature),
            $this->moment($entry->sentAt),
            $this->moment($entry->closesAt),
            $this->moment($entry->deliveryDueAt),
            $this->moment($entry->previousSentAt),
            $this->moment($entry->previousClosesAt),
            $this->moment($entry->previousDeliveryDueAt),
            $this->nullable($entry->reason),
        ];
        $this->execute('INSERT INTO mf_group_link_history(GROUP_ID,KIND,ACTOR_ID,ACTOR_NAME,SIGNATURE,SENT_AT,CLOSES_AT,DELIVERY_DUE_AT,'
            . 'PREVIOUS_SENT_AT,PREVIOUS_CLOSES_AT,PREVIOUS_DELIVERY_DUE_AT,REASON,CREATED_AT) VALUES(' . implode(',', $values) . ',UTC_TIMESTAMP())');
    }

    public function history(int $groupId): array
    {
        $moments = [];
        foreach (['SENT_AT', 'CLOSES_AT', 'DELIVERY_DUE_AT', 'PREVIOUS_SENT_AT', 'PREVIOUS_CLOSES_AT', 'PREVIOUS_DELIVERY_DUE_AT', 'CREATED_AT'] as $column) {
            $moments[] = sprintf(self::MOMENT, $column, $column);
        }
        $result = $this->query('SELECT KIND,ACTOR_ID,ACTOR_NAME,REASON,' . implode(',', $moments)
            . ' FROM mf_group_link_history WHERE GROUP_ID=' . $this->id($groupId) . ' ORDER BY ID');
        $history = [];
        while (false !== ($row = $result->fetch())) {
            $history[] = [
                'kind' => (string)$row['KIND'],
                'actorId' => (int)$row['ACTOR_ID'],
                'actorName' => (string)$row['ACTOR_NAME'],
                'at' => (string)$row['CREATED_AT'],
                'sentAt' => $this->text($row['SENT_AT']),
                'closesAt' => $this->text($row['CLOSES_AT']),
                'deliveryDueAt' => $this->text($row['DELIVERY_DUE_AT']),
                'previousSentAt' => $this->text($row['PREVIOUS_SENT_AT']),
                'previousClosesAt' => $this->text($row['PREVIOUS_CLOSES_AT']),
                'previousDeliveryDueAt' => $this->text($row['PREVIOUS_DELIVERY_DUE_AT']),
                'reason' => $this->text($row['REASON']),
            ];
        }

        return $history;
    }

    public function pendingStaffRequests(array $groupIds): array
    {
        $ids = $this->ids($groupIds);
        if ('' === $ids) {
            return [];
        }
        // ROW is reserved in MySQL 8, hence the sr alias.
        $result = $this->query('SELECT DISTINCT r.ID,sr.GROUP_ID,r.PUBLIC_ID,r.REVISION FROM mf_staff_request_row sr '
            . "INNER JOIN mf_staff_request r ON r.ID=sr.REQUEST_ID WHERE sr.GROUP_ID IN ({$ids}) AND r.STATUS<>'transferred' ORDER BY sr.GROUP_ID,r.ID");
        $pending = [];
        while (false !== ($row = $result->fetch())) {
            $pending[(int)$row['GROUP_ID']][] = [(string)$row['PUBLIC_ID'], (int)$row['REVISION']];
        }

        return $pending;
    }

    public function idempotency(int $actorId, string $resource, string $key): ?array
    {
        $row = $this->query('SELECT PAYLOAD_HASH,RESULT_JSON FROM mf_group_link_idempotency WHERE ACTOR_ID=' . $this->id($actorId)
            . ' AND RESOURCE_KEY=' . $this->quote($resource) . ' AND IDEMPOTENCY_KEY=' . $this->quote($key) . ' FOR UPDATE')->fetch();

        return false === $row ? null : ['payloadHash' => (string)$row['PAYLOAD_HASH'], 'result' => (string)$row['RESULT_JSON']];
    }

    public function remember(int $actorId, string $resource, string $key, string $payloadHash, string $result): void
    {
        $this->execute('INSERT INTO mf_group_link_idempotency(ACTOR_ID,RESOURCE_KEY,IDEMPOTENCY_KEY,PAYLOAD_HASH,RESULT_JSON,CREATED_AT) VALUES('
            . $this->id($actorId) . ',' . $this->quote($resource) . ',' . $this->quote($key) . ',' . $this->quote($payloadHash) . ',' . $this->quote($result) . ',UTC_TIMESTAMP())');
    }

    private function advanceWith(int $groupId, int $expectedRevision, string $assignments): int
    {
        $this->execute('UPDATE mf_group_link SET REVISION=REVISION+1' . $assignments . ',UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID='
            . $this->id($groupId) . ' AND REVISION=' . $this->id($expectedRevision));
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }

        return $expectedRevision + 1;
    }

    /** @param array<string, mixed> $row */
    private function state(array $row): LinkState
    {
        return new LinkState((int)$row['REVISION'], $this->text($row['PREPARED_SIGNATURE']));
    }

    /** @param list<int> $groupIds */
    private function ids(array $groupIds): string
    {
        return implode(',', array_map($this->id(...), array_values(array_unique($groupIds))));
    }

    private function id(int $value): int
    {
        if (1 > $value) {
            throw new \InvalidArgumentException('A positive identifier is required.');
        }

        return $value;
    }

    private function moment(?string $value): string
    {
        if (null === $value) {
            return 'NULL';
        }
        $moment = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
        if (false === $moment) {
            throw new \InvalidArgumentException('An ISO 8601 moment with an offset is required.');
        }

        return $this->quote($moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }

    private function nullable(?string $value): string
    {
        return null === $value ? 'NULL' : $this->quote($value);
    }

    private function text(mixed $value): ?string
    {
        return null === $value ? null : (string)$value;
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
            throw new LinkStorageException('Cannot read group link state.', 0, $error);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $error) {
            throw new LinkStorageException('Cannot persist group link state.', 0, $error);
        }
    }
}
