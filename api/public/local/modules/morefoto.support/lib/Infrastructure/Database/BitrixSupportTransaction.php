<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class BitrixSupportTransaction implements SupportTransactionInterface
{
    public function execute(callable $operation): mixed
    {
        $connection = Application::getConnection();
        $started = false;
        try {
            $connection->queryExecute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $connection->startTransaction();
            $started = true;
            $result = $operation();
            $connection->commitTransaction();

            return $result;
        } catch (\Throwable $error) {
            if ($started) {
                $connection->rollbackTransaction();
            }
            if ($error instanceof HttpException || $error instanceof \InvalidArgumentException) {
                throw $error;
            }

            $previous = $error instanceof \Exception ? $error : new \RuntimeException('Support transaction failed.', 0, $error);
            throw new HttpException('SUPPORT_UNAVAILABLE', 503, $previous);
        }
    }
}
