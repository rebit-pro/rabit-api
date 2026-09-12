<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Institution\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;

final readonly class InstitutionOperationRepository
{
    public function lockInstitution(InstitutionId $id): Result
    {
        return Application::getConnection()->query("SELECT ID,UF_PUBLIC_ID,UF_NAME,UF_ADDRESS,UF_REVISION FROM b_hlbd_mf_institution WHERE UF_PUBLIC_ID='{$id->value}' FOR UPDATE");
    }

    public function find(int $actor, string $operation, string $key): Result
    {
        $c = Application::getConnection();
        $operation = $c->getSqlHelper()->forSql($operation);
        $key = $c->getSqlHelper()->forSql($key);

        return $c->query("SELECT payload_hash,result_json FROM mf_institution_operation WHERE actor_id={$actor} AND operation='{$operation}' AND idempotency_key='{$key}'");
    }

    public function save(int $actor, string $operation, string $key, string $hash, string $result): void
    {
        $c = Application::getConnection();
        $h = $c->getSqlHelper();
        $operation = $h->forSql($operation);
        $key = $h->forSql($key);
        $hash = $h->forSql($hash);
        $result = $h->forSql($result);
        $c->queryExecute("INSERT INTO mf_institution_operation(actor_id,operation,idempotency_key,payload_hash,result_json,created_at) VALUES({$actor},'{$operation}','{$key}','{$hash}','{$result}',UTC_TIMESTAMP())");
    }

    public function record(int $id, int $from, int $to, int $actor, string $operationId, string $delta): void
    {
        $c = Application::getConnection();
        $h = $c->getSqlHelper();
        $operationId = $h->forSql($operationId);
        $delta = $h->forSql($delta);
        $c->queryExecute("INSERT INTO b_hlbd_mf_organization_change(UF_AGGREGATE_ID,UF_FROM_REVISION,UF_TO_REVISION,UF_ACTOR_ID,UF_OPERATION_ID,UF_DELTA,UF_OCCURRED_AT) VALUES({$id},{$from},{$to},{$actor},'{$operationId}','{$delta}',UTC_TIMESTAMP())");
    }
}
