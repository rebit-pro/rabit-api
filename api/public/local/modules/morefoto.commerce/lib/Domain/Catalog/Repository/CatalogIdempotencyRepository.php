<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Catalog\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;

final readonly class CatalogIdempotencyRepository
{
    /** Caller holds the actor identity lock; no two requests of this actor can race a missing key. */
    public function find(int $actorId, string $method, string $resource, IdempotencyKey $key): Result
    {
        return Application::getConnection()->query('SELECT PAYLOAD_HASH, PRODUCT_UUID, RESULT_REVISION FROM mf_catalog_idempotency WHERE ' . $this->scope($actorId, $method, $resource, $key) . ' FOR UPDATE');
    }

    public function save(int $actorId, string $method, string $resource, IdempotencyKey $key, string $hash, string $productId, int $revision): void
    {
        $helper = Application::getConnection()->getSqlHelper();
        Application::getConnection()->queryExecute('INSERT INTO mf_catalog_idempotency SET '
            . 'ACTOR_ID = ' . $actorId . ", HTTP_METHOD = '" . $helper->forSql($method) . "', RESOURCE_KEY = '" . $helper->forSql($resource)
            . "', IDEMPOTENCY_KEY = '" . $key->value . "', PAYLOAD_HASH = '" . $helper->forSql($hash)
            . "', PRODUCT_UUID = '" . $helper->forSql($productId) . "', RESULT_REVISION = " . $revision . ', CREATED_AT = UTC_TIMESTAMP()');
    }

    private function scope(int $actorId, string $method, string $resource, IdempotencyKey $key): string
    {
        $helper = Application::getConnection()->getSqlHelper();

        return 'ACTOR_ID = ' . $actorId . " AND HTTP_METHOD = '" . $helper->forSql($method) . "' AND RESOURCE_KEY = '" . $helper->forSql($resource) . "' AND IDEMPOTENCY_KEY = '" . $key->value . "'";
    }
}
