<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryKindEnum;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\ValueObject\DeliveryPart;
use Morefoto\Files\Domain\Delivery\ValueObject\FileDelivery;
use Morefoto\Files\Domain\Delivery\ValueObject\LetterFile;
use Morefoto\Files\Domain\Delivery\ValueObject\LetterPlan;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;

/**
 * Моменты хранятся в UTC. AUTO_ORDER_ID — одна автоматическая отправка на заказ; (заказ, хеш Idempotency-Key) — одна
 * отправка на попытку покупателя. Письма отправки (J5) лежат в mf_file_delivery_part: состав вложений записан один раз
 * вместе с LETTERS, поэтому LETTERS IS NULL значит «план ещё не записан».
 *
 * @phpstan-type DeliveryRow array{
 *     PUBLIC_ID: string, ORDER_ID: int|string, KIND: string, STATUS: string, RECIPIENT: string,
 *     OPERATION_ID: null|string, CREATED_AT: string, QUEUED_AT: null|string, LETTERS: null|int|string, LINK_ONLY: null|string,
 * }
 * @phpstan-type FileRow array{photoId: string, name: string, mimeType: string, bytes: int, path: string}
 */
final readonly class BitrixDeliveryRepository implements DeliveryRepositoryInterface
{
    private const string COLUMNS = "PUBLIC_ID,ORDER_ID,KIND,STATUS,RECIPIENT,OPERATION_ID,DATE_FORMAT(CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT,
        DATE_FORMAT(QUEUED_AT,'%Y-%m-%d %H:%i:%s') AS QUEUED_AT,LETTERS,LINK_ONLY";

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

    public function plan(string $publicId, LetterPlan $plan, \DateTimeImmutable $now): void
    {
        $connection = Application::getConnection();
        try {
            $connection->startTransaction();
            $connection->queryExecute('UPDATE mf_file_delivery SET LETTERS=' . count($plan->letters) . ',LINK_ONLY=' . $this->text($this->json($plan->linkOnly))
                . ',UPDATED_AT=' . $this->moment($now) . ' WHERE PUBLIC_ID=' . $this->text($publicId) . " AND STATUS='held' AND LETTERS IS NULL");
            // The update locks the delivery row: a parallel sender waits for this plan and then finds it written.
            if (1 === $connection->getAffectedRowsCount()) {
                $id = (int)$connection->query('SELECT ID FROM mf_file_delivery WHERE PUBLIC_ID=' . $this->text($publicId))->fetch()['ID'];
                $rows = [];
                foreach ($plan->letters as $index => $files) {
                    $bytes = array_sum(array_map(static fn(LetterFile $file): int => $file->bytes, $files));
                    $rows[] = '(' . implode(',', [$id, $index + 1, $this->text($this->json($files)), $bytes, $this->moment($now)]) . ')';
                }
                $connection->queryExecute('INSERT INTO mf_file_delivery_part(DELIVERY_ID,NUMBER,FILES,BYTES,CREATED_AT) VALUES' . implode(',', $rows));
            }
            $connection->commitTransaction();
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();
            throw new FilesStorageException('Cannot plan the order email letters.', 0, $error);
        }
    }

    public function parts(string $publicId): array
    {
        $result = $this->query('SELECT p.NUMBER,p.FILES,p.OPERATION_ID FROM mf_file_delivery_part p JOIN mf_file_delivery d ON d.ID=p.DELIVERY_ID WHERE d.PUBLIC_ID='
            . $this->text($publicId) . ' ORDER BY p.NUMBER');
        $parts = [];
        while (false !== ($row = $result->fetch())) {
            /** @var array{NUMBER: int|string, FILES: string, OPERATION_ID: null|string} $row */
            $parts[] = new DeliveryPart((int)$row['NUMBER'], $this->files($row['FILES']), $row['OPERATION_ID']);
        }

        return $parts;
    }

    public function markPartQueued(string $publicId, int $number, string $operationId, \DateTimeImmutable $now): void
    {
        $this->execute('UPDATE mf_file_delivery_part p JOIN mf_file_delivery d ON d.ID=p.DELIVERY_ID SET p.OPERATION_ID=' . $this->text($operationId)
            . ',p.QUEUED_AT=' . $this->moment($now) . ' WHERE d.PUBLIC_ID=' . $this->text($publicId) . ' AND p.NUMBER=' . $number . ' AND p.OPERATION_ID IS NULL');
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
            letters: null === $row['LETTERS'] ? null : (int)$row['LETTERS'],
            linkOnly: null === $row['LINK_ONLY'] ? [] : $this->files($row['LINK_ONLY']),
        );
    }

    /** @param list<LetterFile> $files */
    private function json(array $files): string
    {
        return json_encode($files, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return list<LetterFile> */
    private function files(string $json): array
    {
        try {
            /** @var list<FileRow> $rows */
            $rows = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new FilesStorageException('Cannot read the order email letters.', 0, $error);
        }

        return array_map(static fn(array $row): LetterFile => new LetterFile($row['photoId'], $row['name'], $row['mimeType'], (int)$row['bytes'], $row['path']), $rows);
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
