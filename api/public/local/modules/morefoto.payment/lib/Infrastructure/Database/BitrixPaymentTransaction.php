<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;

final readonly class BitrixPaymentTransaction implements PaymentTransactionInterface
{
    private const int ATTEMPTS = 3;

    public function execute(callable $operation): mixed
    {
        $connection = Application::getConnection();
        for ($attempt = 1;; ++$attempt) {
            $started = false;
            try {
                $connection->startTransaction();
                $started = true;
                $result = $operation();
                $connection->commitTransaction();

                return $result;
            } catch (\Throwable $error) {
                if ($started) {
                    $connection->rollbackTransaction();
                }
                if (!$started || self::ATTEMPTS <= $attempt || !$this->retryable($error)) {
                    throw $error;
                }
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
