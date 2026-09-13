<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Calendar\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Organization\Domain\Calendar\Exception\CalendarStorageException;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use Ramsey\Uuid\Uuid;

final readonly class GroupCalendarRepository
{
    public function lockParents(string $publicId): int
    {
        self::validateId($publicId);
        try {
            $connection = Application::getConnection();
            $parents = $connection->query("SELECT s.ID AS SHOOT_ID,s.UF_INSTITUTION_ID AS INSTITUTION_ID FROM b_hlbd_mf_group g JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID WHERE g.UF_PUBLIC_ID='{$publicId}'")->fetch();
            if (false === $parents) {
                return 0;
            }
            $institutionId = (int)$parents['INSTITUTION_ID'];
            $shootId = (int)$parents['SHOOT_ID'];
            // Parent references are immutable in C3; acquire the global order before any profiles/Auth.
            $connection->query("SELECT ID FROM b_hlbd_mf_institution WHERE ID={$institutionId} FOR UPDATE")->fetch();
            $connection->query("SELECT ID FROM b_hlbd_mf_shoot WHERE ID={$shootId} FOR UPDATE")->fetch();
            $connection->query("SELECT ID FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$publicId}' FOR UPDATE")->fetch();

            return $institutionId;
        } catch (\Throwable $exception) {
            throw new CalendarStorageException('Cannot lock group calendar.', 0, $exception);
        }
    }

    public function find(string $publicId, bool $forUpdate = false): Result
    {
        self::validateId($publicId);
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        try {
            return Application::getConnection()->query("SELECT ID,UF_PUBLIC_ID,UF_REVISION,UF_TIMEZONE,DATE_FORMAT(UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS UF_SENT_AT,DATE_FORMAT(UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS UF_CLOSES_AT,DATE_FORMAT(UF_DELIVERY_DUE_AT,'%Y-%m-%d %H:%i:%s') AS UF_DELIVERY_DUE_AT FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$publicId}'{$lock}");
        } catch (\Throwable $exception) {
            throw new CalendarStorageException('Cannot read group calendar.', 0, $exception);
        }
    }

    /** Current read preserves replay even if the caller previously established a REPEATABLE READ snapshot. */
    public function findOperation(int $actor, string $operation, string $key): Result
    {
        if (1 > $actor) {
            throw new \InvalidArgumentException('A positive actor ID is required.');
        }
        try {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $operation = $helper->forSql($operation);
            $key = $helper->forSql($key);

            return $connection->query("SELECT payload_hash,result_json FROM mf_institution_operation WHERE actor_id={$actor} AND operation='{$operation}' AND idempotency_key='{$key}' FOR UPDATE");
        } catch (\Throwable $exception) {
            throw new CalendarStorageException('Cannot read group calendar operation.', 0, $exception);
        }
    }

    public function save(int $id, int $expectedRevision, GroupCalendar $calendar): bool
    {
        if (1 > $id || 1 > $expectedRevision || 2147483646 < $expectedRevision || null === $calendar->sentAt || null === $calendar->closesAt || null === $calendar->deliveryDueAt) {
            throw new \InvalidArgumentException('A complete calendar and incrementable group revision are required.');
        }
        $utc = new \DateTimeZone('UTC');
        $sentAt = $calendar->sentAt->setTimezone($utc)->format('Y-m-d H:i:s');
        $closesAt = $calendar->closesAt->setTimezone($utc)->format('Y-m-d H:i:s');
        $deliveryDueAt = $calendar->deliveryDueAt->setTimezone($utc)->format('Y-m-d H:i:s');
        try {
            $connection = Application::getConnection();
            $connection->queryExecute("UPDATE b_hlbd_mf_group SET UF_SENT_AT='{$sentAt}',UF_CLOSES_AT='{$closesAt}',UF_DELIVERY_DUE_AT='{$deliveryDueAt}',UF_REVISION=UF_REVISION+1,UF_UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$id} AND UF_REVISION={$expectedRevision}");

            return 1 === $connection->getAffectedRowsCount();
        } catch (\Throwable $exception) {
            throw new CalendarStorageException('Cannot save group calendar.', 0, $exception);
        }
    }

    public function record(int $id, int $from, int $to, int $actor, string $operationId, string $delta): void
    {
        if (1 > $id || 1 > $from || $from + 1 !== $to || 1 > $actor) {
            throw new \InvalidArgumentException('Invalid group calendar history.');
        }
        try {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $operationId = $helper->forSql($operationId);
            $delta = $helper->forSql($delta);
            $connection->queryExecute("INSERT INTO b_hlbd_mf_organization_change(UF_AGGREGATE_TYPE,UF_AGGREGATE_ID,UF_FROM_REVISION,UF_TO_REVISION,UF_ACTOR_ID,UF_OPERATION_ID,UF_DELTA,UF_OCCURRED_AT) VALUES('group',{$id},{$from},{$to},{$actor},'{$operationId}','{$delta}',UTC_TIMESTAMP())");
        } catch (\Throwable $exception) {
            throw new CalendarStorageException('Cannot record group calendar history.', 0, $exception);
        }
    }

    private static function validateId(string $publicId): void
    {
        if (strtolower($publicId) !== $publicId || !Uuid::isValid($publicId)) {
            throw new \InvalidArgumentException('Invalid group ID.');
        }
    }
}
