<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;

/** Общие приёмы SQL репозиториев Support: экранирование, UTC-моменты и перевод ошибок БД в RuntimeException. */
final readonly class SupportSql
{
    public const string ISO_MOMENT = "DATE_FORMAT(%s,'%%Y-%%m-%%dT%%H:%%i:%%sZ')";

    public function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new \RuntimeException('Cannot read support state.', 0, $error);
        }
    }

    public function execute(string $sql): int
    {
        try {
            $connection = Application::getConnection();
            $connection->queryExecute($sql);

            return $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new \RuntimeException('Cannot persist support state.', 0, $error);
        }
    }

    public function insert(string $sql): int
    {
        $this->execute($sql);

        return (int)Application::getConnection()->getInsertedId();
    }

    public function quote(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    public function moment(\DateTimeImmutable $moment): string
    {
        return "'" . $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') . "'";
    }

    public function id(int $value): int
    {
        if (1 > $value) {
            throw new \InvalidArgumentException('A positive identifier is required.');
        }

        return $value;
    }
}
