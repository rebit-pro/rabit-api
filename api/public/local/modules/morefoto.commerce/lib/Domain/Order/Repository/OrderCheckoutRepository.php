<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Repository;

use Bitrix\Main\Application;
use Morefoto\Commerce\Domain\Order\Exception\OrderStorageException;

final readonly class OrderCheckoutRepository
{
    /**
     * Резервирует ключ повтора вставкой и держит строку до конца транзакции вызывающего сценария.
     * Одновременный запрос с тем же ключом ждёт commit/rollback первого и видит его итог.
     *
     * @return array{REQUEST_HASH: string, ORDER_ID: null|int|string, SEALED_KEY: null|string}
     */
    public function reserve(string $galleryHash, string $keyHash, string $requestHash): array
    {
        try {
            $connection = Application::getConnection();
            $connection->queryExecute("INSERT INTO mf_order_checkout(GALLERY_HASH,KEY_HASH,REQUEST_HASH,ORDER_ID,SEALED_KEY,CREATED_AT)
                VALUES('{$galleryHash}','{$keyHash}','{$requestHash}',NULL,NULL,UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE GALLERY_HASH=GALLERY_HASH");
            /** @var array{REQUEST_HASH: string, ORDER_ID: null|int|string, SEALED_KEY: null|string}|false $row */
            $row = $connection->query("SELECT REQUEST_HASH,ORDER_ID,SEALED_KEY FROM mf_order_checkout
                WHERE GALLERY_HASH='{$galleryHash}' AND KEY_HASH='{$keyHash}' FOR UPDATE")->fetch();
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot reserve checkout key.', 0, $error);
        }
        if (false === $row) {
            throw new OrderStorageException('Checkout reservation disappeared.');
        }

        return $row;
    }

    public function complete(string $galleryHash, string $keyHash, int $orderId, string $sealedKey): void
    {
        try {
            $connection = Application::getConnection();
            $sealed = $connection->getSqlHelper()->forSql($sealedKey);
            $connection->queryExecute("UPDATE mf_order_checkout SET ORDER_ID={$orderId},SEALED_KEY='{$sealed}'
                WHERE GALLERY_HASH='{$galleryHash}' AND KEY_HASH='{$keyHash}' AND ORDER_ID IS NULL");
            $affected = $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot complete checkout receipt.', 0, $error);
        }
        if (1 !== $affected) {
            throw new OrderStorageException('Checkout receipt was completed concurrently.');
        }
    }
}
