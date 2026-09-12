<?php

declare(strict_types=1);

namespace Morefoto\Organization\Infrastructure\Persistence;

use Bitrix\Main\Application;
use Rebit\Share\Shared\Exception\HttpException;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionStorageException;

final readonly class InstitutionTransaction implements InstitutionTransactionInterface
{
    public function execute(callable $operation): mixed
    {
        $connection = Application::getConnection();
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $started = false;
            try {
                // Reject an ambient transaction before touching caller-owned work.
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
                if ($started && $attempt < 2 && $this->isDeadlock($exception)) {
                    continue;
                }
                if ($exception instanceof HttpException || $exception instanceof \DomainException || $exception instanceof \InvalidArgumentException) {
                    throw $exception;
                }
                throw new InstitutionStorageException('Institution transaction failed.', 0, $exception);
            }
        }
        throw new InstitutionStorageException('Transaction retry exhausted.');
    }

    private function isDeadlock(\Throwable $exception): bool
    {
        do {
            if (str_contains($exception->getMessage(), 'Deadlock found')) {
                return true;
            }
            $exception = $exception->getPrevious();
        } while (null !== $exception);

        return false;
    }
}
