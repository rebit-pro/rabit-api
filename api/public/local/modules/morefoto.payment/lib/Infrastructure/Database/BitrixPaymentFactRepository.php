<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Payment\Domain\Payment\Exception\PaymentStorageException;
use Morefoto\Payment\Domain\Payment\Repository\PaymentFactRepositoryInterface;

final readonly class BitrixPaymentFactRepository implements PaymentFactRepositoryInterface
{
    public function insert(array $fact): bool
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $text = static fn(string $value): string => "'" . $helper->forSql($value) . "'";
        try {
            // An existing fact of the same payment is kept as is: a repeated confirmation never rewrites money.
            $connection->queryExecute('INSERT INTO mf_payment_fact(ATTEMPT_ID,ORDER_ID,PROVIDER,PROVIDER_PAYMENT_ID,AMOUNT,INCOME_AMOUNT,PAID_AT,LATE_PAYMENT,
                CONFIRMED_BY,CREATED_AT) VALUES(' . implode(',', [
                $fact['ATTEMPT_ID'], $fact['ORDER_ID'], $text($fact['PROVIDER']), $text($fact['PROVIDER_PAYMENT_ID']), $fact['AMOUNT'],
                $fact['INCOME_AMOUNT'] ?? 'NULL', $text($fact['PAID_AT']), (int)$fact['LATE_PAYMENT'], $text($fact['CONFIRMED_BY']), $text($fact['CREATED_AT']),
            ]) . ') ON DUPLICATE KEY UPDATE ID=ID');

            return 1 === $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot persist payment fact.', 0, $error);
        }
    }

    public function forOrder(int $orderId): array
    {
        try {
            $result = Application::getConnection()->query("SELECT ATTEMPT_ID,ORDER_ID,PROVIDER,PROVIDER_PAYMENT_ID,AMOUNT,INCOME_AMOUNT,
                DATE_FORMAT(PAID_AT,'%Y-%m-%d %H:%i:%s') AS PAID_AT,LATE_PAYMENT,CONFIRMED_BY,DATE_FORMAT(CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT
                FROM mf_payment_fact WHERE ORDER_ID={$orderId} ORDER BY ID");
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot read payment facts.', 0, $error);
        }
        $facts = [];
        while (false !== ($row = $result->fetch())) {
            $facts[] = [
                'ATTEMPT_ID' => (int)$row['ATTEMPT_ID'],
                'ORDER_ID' => (int)$row['ORDER_ID'],
                'PROVIDER' => (string)$row['PROVIDER'],
                'PROVIDER_PAYMENT_ID' => (string)$row['PROVIDER_PAYMENT_ID'],
                'AMOUNT' => (int)$row['AMOUNT'],
                'INCOME_AMOUNT' => null === $row['INCOME_AMOUNT'] ? null : (int)$row['INCOME_AMOUNT'],
                'PAID_AT' => (string)$row['PAID_AT'],
                'LATE_PAYMENT' => 1 === (int)$row['LATE_PAYMENT'],
                'CONFIRMED_BY' => (string)$row['CONFIRMED_BY'],
                'CREATED_AT' => (string)$row['CREATED_AT'],
            ];
        }

        return $facts;
    }
}
