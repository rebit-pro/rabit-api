<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryKindEnum;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\ValueObject\FileDelivery;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;

/**
 * Моменты хранятся в UTC. AUTO_ORDER_ID — одно автоматическое письмо на заказ; (заказ, хеш Idempotency-Key) — одно
 * письмо на попытку покупателя.
 *
 * @phpstan-type DeliveryRow array{
 *     PUBLIC_ID: string, ORDER_ID: int|string, KIND: string, STATUS: string, RECIPIENT: string,
 *     OPERATION_ID: null|string, CREATED_AT: string, QUEUED_AT: null|string,
 * }
 */
final readonly class BitrixDeliveryRepository implements DeliveryRepositoryInterface
{
    private const string COLUMNS = "PUBLIC_ID,ORDER_ID,KIND,STATUS,RECIPIENT,OPERATION_ID,DATE_FORMAT(CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT,
        DATE_FORMAT(QUEUED_AT,'%Y-%m-%d %H:%i:%s') AS QUEUED_AT";

    public function cursor(): array
    {
        /** @var array{AT: string, ORDER_ID: int|string}|false $row */
        $row = $this->query("SELECT DATE_FORMAT(PAID_RECORDED_AT,'%Y-%m-%d %H:%i:%s') AS AT,ORDER_ID FROM mf_file_delivery_cursor WHERE ID=1")->fetch();
        if (false === $row) {
            throw new FilesStorageException('Apply the J2 migration first: the delivery cursor is missing.');
        }

        return ['at' => $this->instant($row['AT']), 'orderId' => (int)$row['ORDER_ID']];
    }

    public function advance(\DateTimeImmutable $at, int $orderId): void
    {
        $this->execute('UPDATE mf_file_delivery_cursor SET PAID_RECORDED_AT=' . $this->moment($at) . ',ORDER_ID=' . $orderId . ',UPDATED_AT=UTC_TIMESTAMP() WHERE ID=1');
    }

    public function createAuto(string $publicId, int $orderId, string $recipient, \DateTimeImmutable $now): bool
    {
        return 1 === $this->execute('INSERT IGNORE INTO mf_file_delivery(PUBLIC_ID,ORDER_ID,KIND,STATUS,RECIPIENT,CREATED_AT,UPDATED_AT) VALUES('
            . implode(',', [$this->text($publicId), $orderId, "'auto'", "'held'", $this->text($recipient), $this->moment($now), $this->moment($now)]) . ')');
    }

    public function createManual(string $publicId, int $orderId, string $recipient, string $idempotencyHash, \DateTimeImmutable $now): bool
    {
        return 1 === $this->execute('INSERT IGNORE INTO mf_file_delivery(PUBLIC_ID,ORDER_ID,KIND,STATUS,RECIPIENT,IDEMPOTENCY_HASH,CREATED_AT,UPDATED_AT) VALUES('
            . implode(',', [$this->text($publicId), $orderId, "'manual'", "'held'", $this->text($recipient), $this->text($idempotencyHash), $this->moment($now), $this->moment($now)]) . ')');
    }

    public function find(string $publicId): ?FileDelivery
    {
        return $this->one('PUBLIC_ID=' . $this->text($publicId));
    }

    public function findByIdempotency(int $orderId, string $idempotencyHash): ?FileDelivery
    {
        return $this->one("ORDER_ID={$orderId} AND IDEMPOTENCY_HASH=" . $this->text($idempotencyHash));
    }

    public function latest(int $orderId): ?FileDelivery
    {
        return $this->one("ORDER_ID={$orderId} ORDER BY CREATED_AT DESC,ID DESC LIMIT 1");
    }

    public function countSince(int $orderId, \DateTimeImmutable $since): int
    {
        $row = $this->query("SELECT COUNT(*) AS TOTAL FROM mf_file_delivery WHERE ORDER_ID={$orderId} AND CREATED_AT>" . $this->moment($since))->fetch();

        return false === $row ? 0 : (int)$row['TOTAL'];
    }

    public function held(int $limit): array
    {
        $result = $this->query('SELECT ' . self::COLUMNS . " FROM mf_file_delivery WHERE STATUS='held' ORDER BY ID LIMIT {$limit}");
        $deliveries = [];
        while (false !== ($row = $result->fetch())) {
            /** @var DeliveryRow $row */
            $deliveries[] = $this->delivery($row);
        }

        return $deliveries;
    }

    public function markQueued(string $publicId, string $operationId, \DateTimeImmutable $now): void
    {
        $this->execute("UPDATE mf_file_delivery SET STATUS='queued',OPERATION_ID=" . $this->text($operationId) . ',QUEUED_AT=' . $this->moment($now)
            . ',UPDATED_AT=' . $this->moment($now) . ' WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='held'");
    }

    public function markCancelled(string $publicId, \DateTimeImmutable $now): void
    {
        $this->execute("UPDATE mf_file_delivery SET STATUS='cancelled',UPDATED_AT=" . $this->moment($now) . ' WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='held'");
    }

    private function one(string $where): ?FileDelivery
    {
        /** @var DeliveryRow|false $row */
        $row = $this->query('SELECT ' . self::COLUMNS . ' FROM mf_file_delivery WHERE ' . $where . (str_contains($where, 'LIMIT') ? '' : ' LIMIT 1'))->fetch();

        return false === $row ? null : $this->delivery($row);
    }

    /** @param DeliveryRow $row */
    private function delivery(array $row): FileDelivery
    {
        return new FileDelivery(
            publicId: $row['PUBLIC_ID'],
            orderId: (int)$row['ORDER_ID'],
            kind: DeliveryKindEnum::from($row['KIND']),
            status: DeliveryStatusEnum::from($row['STATUS']),
            recipient: $row['RECIPIENT'],
            operationId: $row['OPERATION_ID'],
            createdAt: $this->instant($row['CREATED_AT']),
            queuedAt: null === $row['QUEUED_AT'] ? null : $this->instant($row['QUEUED_AT']),
        );
    }

    private function instant(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private function moment(\DateTimeImmutable $value): string
    {
        return "'" . $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') . "'";
    }

    private function text(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot read order emails.', 0, $error);
        }
    }

    private function execute(string $sql): int
    {
        try {
            $connection = Application::getConnection();
            $connection->queryExecute($sql);

            return $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot change order emails.', 0, $error);
        }
    }
}
