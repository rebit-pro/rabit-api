<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Storefront;

use Bitrix\Main\Application;
use Morefoto\Commerce\Application\Storefront\Contract\QuoteTransactionInterface;

final readonly class QuoteTransaction implements QuoteTransactionInterface
{
    public function execute(callable $operation): mixed
    {
        $connection = Application::getConnection();
        $connection->queryExecute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $connection->startTransaction();
        try {
            $result = $operation();
            $connection->commitTransaction();

            return $result;
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();
            throw $error;
        }
    }
}
