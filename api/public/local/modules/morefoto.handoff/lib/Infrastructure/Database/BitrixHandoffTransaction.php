<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class BitrixHandoffTransaction implements HandoffTransactionInterface
{
    /** @param positive-int $attempts повторы всей операции после deadlock или lock wait; команда должна перечитывать состояние */
    public function __construct(private int $attempts = 1) {}

    public function execute(callable $operation): mixed
    {
        $connection = Application::getConnection();
        for ($attempt = 1;; ++$attempt) {
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
                if ($started && $attempt < $this->attempts && $this->retryable($error)) {
                    continue;
                }
                if ($error instanceof HttpException || $error instanceof \InvalidArgumentException) {
                    throw $error;
                }

                $previous = $error instanceof \Exception ? $error : new \RuntimeException('Handoff transaction failed.', 0, $error);
                throw new HttpException('HANDOFF_UNAVAILABLE', 503, $previous);
            }
        }
    }

    private function retryable(\Throwable $error): bool
    {
        for ($current = $error; null !== $current; $current = $current->getPrevious()) {
            if (str_contains($current->getMessage(), 'Deadlock found') || str_contains($current->getMessage(), 'Lock wait timeout exceeded')) {
                return true;
            }
        }

        return false;
    }
}
