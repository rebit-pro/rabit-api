<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class BitrixHandoffTransaction implements HandoffTransactionInterface
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

            $previous = $error instanceof \Exception ? $error : new \RuntimeException('Handoff transaction failed.', 0, $error);
            throw new HttpException('HANDOFF_UNAVAILABLE', 503, $previous);
        }
    }
}
