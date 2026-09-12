<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Adapter;

use Bitrix\Main\Application;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogException;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogStorageException;

final readonly class BitrixCatalogTransaction implements CatalogTransactionInterface
{
    public function execute(callable $operation): mixed
    {
        $connection = Application::getConnection();
        $started = false;
        try {
            // Each scenario owns its transaction. MySQL rejects this if an ambient
            // transaction is active, before we can commit or roll back caller work.
            $connection->queryExecute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $connection->startTransaction();
            $started = true;
            $result = $operation();
            $connection->commitTransaction();

            return $result;
        } catch (\Throwable $exception) {
            if ($started) {
                $connection->rollbackTransaction();
            }
            if ($exception instanceof CatalogException || $exception instanceof CatalogAccessException) {
                throw $exception;
            }
            throw new CatalogStorageException('Catalogue persistence failed.', 0, $exception);
        }
    }
}
