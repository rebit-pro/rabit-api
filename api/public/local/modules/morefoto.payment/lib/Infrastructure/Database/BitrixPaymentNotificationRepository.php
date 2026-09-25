<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Payment\Domain\Payment\Exception\PaymentStorageException;
use Morefoto\Payment\Domain\Payment\Repository\PaymentNotificationRepositoryInterface;

final readonly class BitrixPaymentNotificationRepository implements PaymentNotificationRepositoryInterface
{
    public function register(string $provider, string $eventKey, string $event, string $objectId, string $receivedAt): bool
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $text = static fn(string $value): string => "'" . $helper->forSql($value) . "'";
        try {
            $connection->queryExecute('INSERT INTO mf_payment_notification(PROVIDER,EVENT_KEY,EVENT,PROVIDER_OBJECT_ID,RECEIVED_AT) VALUES('
                . implode(',', [$text($provider), $text($eventKey), $text($event), $text($objectId), $text($receivedAt)]) . ') ON DUPLICATE KEY UPDATE ID=ID');
            $row = $connection->query('SELECT PROCESSED_AT FROM mf_payment_notification WHERE PROVIDER=' . $text($provider) . ' AND EVENT_KEY=' . $text($eventKey))->fetch();
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot register payment notification.', 0, $error);
        }

        return is_array($row) && null !== $row['PROCESSED_AT'];
    }

    public function complete(string $provider, string $eventKey, string $result, string $processedAt): void
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        try {
            $connection->queryExecute("UPDATE mf_payment_notification SET PROCESSED_AT='" . $helper->forSql($processedAt) . "',RESULT='" . $helper->forSql($result)
                . "' WHERE PROVIDER='" . $helper->forSql($provider) . "' AND EVENT_KEY='" . $helper->forSql($eventKey) . "'");
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot complete payment notification.', 0, $error);
        }
    }
}
