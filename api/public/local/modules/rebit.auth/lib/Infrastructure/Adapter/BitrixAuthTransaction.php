<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Bitrix\Main\Application;
use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;

final readonly class BitrixAuthTransaction implements AuthTransactionInterface
{
    public function run(callable $operation): mixed
    {
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            $result = $operation();
            $connection->commitTransaction();

            return $result;
        } catch (\Throwable $exception) {
            $connection->rollbackTransaction();

            throw $exception;
        }
    }
}
