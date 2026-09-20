<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class BitrixMediaTransaction implements MediaTransactionInterface
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
        } catch (\Throwable $exception) {
            if ($started) {
                $connection->rollbackTransaction();
            }
            if ($exception instanceof HttpException || $exception instanceof MediaStorageException) {
                throw $exception;
            }

            throw new MediaStorageException('Cannot update media assignment state.', 0, $exception);
        }
    }
}
