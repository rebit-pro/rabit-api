<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsMutationOutputDto;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsStorageException;

final readonly class ConditionsIdempotencyRepository
{
    public function find(int $actorId, string $resource, IdempotencyKey $key): Result
    {
        return $this->query('SELECT PAYLOAD_HASH,RESULT_REVISION,RESULT_CATALOG_REVISION,RESULT_CONDITIONS_REVISION FROM mf_conditions_idempotency WHERE '
            . $this->scope($actorId, $resource, $key) . ' FOR UPDATE');
    }

    public function save(int $actorId, string $resource, IdempotencyKey $key, string $hash, ConditionsMutationOutputDto $result): void
    {
        $helper = Application::getConnection()->getSqlHelper();
        $this->execute('INSERT INTO mf_conditions_idempotency(ACTOR_ID,RESOURCE_KEY,IDEMPOTENCY_KEY,PAYLOAD_HASH,RESULT_REVISION,RESULT_CATALOG_REVISION,RESULT_CONDITIONS_REVISION,CREATED_AT) VALUES('
            . $actorId . ",'" . $helper->forSql($resource) . "','" . $key->value . "','" . $helper->forSql($hash) . "',"
            . $result->revision . ',' . $result->catalogRevision . ',' . $result->conditionsRevision . ',UTC_TIMESTAMP())');
    }

    private function scope(int $actorId, string $resource, IdempotencyKey $key): string
    {
        if (1 > $actorId) {
            throw new \InvalidArgumentException('Positive actor ID required.');
        }
        $resource = Application::getConnection()->getSqlHelper()->forSql($resource);

        return "ACTOR_ID={$actorId} AND RESOURCE_KEY='{$resource}' AND IDEMPOTENCY_KEY='{$key->value}'";
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $exception) {
            throw new ConditionsStorageException('Cannot read condition idempotency state.', 0, $exception);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $exception) {
            throw new ConditionsStorageException('Cannot persist condition idempotency state.', 0, $exception);
        }
    }
}
