<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Persistence;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Rebit\Notification\Application\Delivery\Contract\DeliveryOperationRepositoryInterface;
use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\NotificationDeduplicationConflictException;

final readonly class DeliveryOperationRepository implements DeliveryOperationRepositoryInterface
{
    private const string OPERATION_TABLE = 'b_rebit_notification_operation';
    private const string ATTEMPT_TABLE = 'b_rebit_notification_attempt';

    public function createOrGet(
        string $id,
        EmailNotificationInputDto $input,
        string $payloadHash,
        \DateTimeImmutable $now,
    ): DeliveryOperationDto {
        return $this->transaction(function(Connection $connection) use ($id, $input, $payloadHash, $now): DeliveryOperationDto {
            $timestamp = $this->date($now);
            $connection->queryExecute(sprintf(
                'INSERT INTO %s (ID,CONSUMER_KEY,DEDUP_KEY,PAYLOAD_HASH,CHANNEL,RECIPIENT,SUBJECT,BODY,BODY_HTML,STATUS,ATTEMPTS,MAX_ATTEMPTS,CREATED_AT,UPDATED_AT) '
                . "VALUES(%s,%s,%s,%s,'email',%s,%s,%s,%s,'pending',0,%d,%s,%s) "
                . 'ON DUPLICATE KEY UPDATE ID=ID',
                self::OPERATION_TABLE,
                $this->quote($id),
                $this->quote($input->consumer),
                $this->quote($input->deduplicationKey),
                $this->quote($payloadHash),
                $this->quote($input->recipient),
                $this->quote($input->subject),
                $this->quote($input->body),
                null === $input->bodyHtml ? 'NULL' : $this->quote($input->bodyHtml),
                $input->maxAttempts,
                $this->quote($timestamp),
                $this->quote($timestamp),
            ));
            $row = $connection->query(sprintf(
                'SELECT * FROM %s WHERE CONSUMER_KEY=%s AND DEDUP_KEY=%s LIMIT 1 FOR UPDATE',
                self::OPERATION_TABLE,
                $this->quote($input->consumer),
                $this->quote($input->deduplicationKey),
            ))->fetch();
            if (!is_array($row)) {
                throw new \RuntimeException('Cannot resolve notification operation.');
            }
            if (!hash_equals((string)$row['PAYLOAD_HASH'], $payloadHash)) {
                throw new NotificationDeduplicationConflictException('Notification deduplication key was reused with another payload.');
            }

            return $this->dto($row);
        });
    }

    public function find(string $id): ?DeliveryOperationDto
    {
        $row = Application::getConnection()->query(sprintf(
            'SELECT * FROM %s WHERE ID=%s LIMIT 1',
            self::OPERATION_TABLE,
            $this->quote($id),
        ))->fetch();

        return is_array($row) ? $this->dto($row) : null;
    }

    public function startAttempt(
        string $id,
        \DateTimeImmutable $now,
        \DateTimeImmutable $staleBefore,
    ): ?DeliveryOperationDto {
        return $this->transaction(function(Connection $connection) use ($id, $now, $staleBefore): ?DeliveryOperationDto {
            $row = $connection->query(sprintf(
                'SELECT * FROM %s WHERE ID=%s LIMIT 1 FOR UPDATE',
                self::OPERATION_TABLE,
                $this->quote($id),
            ))->fetch();
            if (!is_array($row)) {
                return null;
            }
            $status = (string)$row['STATUS'];
            if ('processing' === $status) {
                $startedAt = (string)($row['PROCESSING_STARTED_AT'] ?? '');
                if ('' !== $startedAt && $startedAt <= $this->date($staleBefore)) {
                    $this->markUnknownInTransaction(
                        $connection,
                        (string)$row['ID'],
                        (int)$row['ATTEMPTS'],
                        'processing_lease_expired',
                        $now,
                    );
                }

                return null;
            }
            if (!in_array($status, ['pending', 'retryWait'], true)
                || (int)$row['ATTEMPTS'] >= (int)$row['MAX_ATTEMPTS']
                || ('retryWait' === $status && null !== $row['NEXT_ATTEMPT_AT'] && (string)$row['NEXT_ATTEMPT_AT'] > $this->date($now))) {
                return null;
            }
            $attempt = (int)$row['ATTEMPTS'] + 1;
            $timestamp = $this->date($now);
            $connection->queryExecute(sprintf(
                "UPDATE %s SET STATUS='processing',ATTEMPTS=%d,NEXT_ATTEMPT_AT=NULL,PROCESSING_STARTED_AT=%s,LAST_ERROR_CODE=NULL,UPDATED_AT=%s "
                . 'WHERE ID=%s',
                self::OPERATION_TABLE,
                $attempt,
                $this->quote($timestamp),
                $this->quote($timestamp),
                $this->quote($id),
            ));
            $connection->queryExecute(sprintf(
                "INSERT INTO %s (OPERATION_ID,ATTEMPT_NO,STATUS,STARTED_AT) VALUES(%s,%d,'started',%s)",
                self::ATTEMPT_TABLE,
                $this->quote($id),
                $attempt,
                $this->quote($timestamp),
            ));
            $row['STATUS'] = 'processing';
            $row['ATTEMPTS'] = $attempt;
            $row['NEXT_ATTEMPT_AT'] = null;
            $row['PROCESSING_STARTED_AT'] = $timestamp;
            $row['LAST_ERROR_CODE'] = null;

            return $this->dto($row);
        });
    }

    public function markAccepted(string $id, int $attempt, \DateTimeImmutable $now): void
    {
        $this->complete(
            id: $id,
            attempt: $attempt,
            status: 'accepted',
            errorCode: null,
            nextAttemptAt: null,
            now: $now,
        );
    }

    public function markRejected(
        string $id,
        int $attempt,
        string $errorCode,
        ?\DateTimeImmutable $nextAttemptAt,
        \DateTimeImmutable $now,
    ): void {
        $this->complete(
            id: $id,
            attempt: $attempt,
            status: null === $nextAttemptAt ? 'failed' : 'retryWait',
            errorCode: $errorCode,
            nextAttemptAt: $nextAttemptAt,
            now: $now,
        );
    }

    public function markUnknown(string $id, int $attempt, string $errorCode, \DateTimeImmutable $now): void
    {
        $this->transaction(function(Connection $connection) use ($id, $attempt, $errorCode, $now): void {
            $this->markUnknownInTransaction($connection, $id, $attempt, $errorCode, $now);
        });
    }

    public function recoverStale(\DateTimeImmutable $staleBefore, \DateTimeImmutable $now): int
    {
        return $this->transaction(function(Connection $connection) use ($staleBefore, $now): int {
            $result = $connection->query(sprintf(
                "SELECT ID,ATTEMPTS FROM %s WHERE STATUS='processing' AND PROCESSING_STARTED_AT<=%s ORDER BY UPDATED_AT,ID LIMIT 500 FOR UPDATE",
                self::OPERATION_TABLE,
                $this->quote($this->date($staleBefore)),
            ));
            $recovered = 0;
            while (false !== ($row = $result->fetch())) {
                $this->markUnknownInTransaction(
                    $connection,
                    (string)$row['ID'],
                    (int)$row['ATTEMPTS'],
                    'processing_lease_expired',
                    $now,
                );
                ++$recovered;
            }

            return $recovered;
        });
    }

    public function recoverUnknown(int $limit, \DateTimeImmutable $now): int
    {
        return $this->transaction(function(Connection $connection) use ($limit, $now): int {
            $result = $connection->query(sprintf(
                "SELECT ID FROM %s WHERE STATUS='unknown' AND ATTEMPTS<MAX_ATTEMPTS ORDER BY UPDATED_AT,ID LIMIT %d FOR UPDATE",
                self::OPERATION_TABLE,
                $limit,
            ));
            $ids = [];
            while (false !== ($row = $result->fetch())) {
                $ids[] = (string)$row['ID'];
            }
            if ([] === $ids) {
                return 0;
            }
            $quoted = implode(',', array_map(fn(string $id): string => $this->quote($id), $ids));
            $timestamp = $this->quote($this->date($now));
            $connection->queryExecute(
                'UPDATE ' . self::OPERATION_TABLE . " SET STATUS='retryWait',NEXT_ATTEMPT_AT={$timestamp},PROCESSING_STARTED_AT=NULL,"
                . "LAST_ERROR_CODE='unknown_retry_confirmed',UPDATED_AT={$timestamp} WHERE ID IN ({$quoted}) AND STATUS='unknown'",
            );

            return count($ids);
        });
    }

    public function dueOperationIds(int $limit, \DateTimeImmutable $now): array
    {
        $result = Application::getConnection()->query(sprintf(
            "SELECT ID FROM %s WHERE ATTEMPTS<MAX_ATTEMPTS AND (STATUS='pending' OR (STATUS='retryWait' AND NEXT_ATTEMPT_AT<=%s)) "
            . 'ORDER BY COALESCE(NEXT_ATTEMPT_AT,CREATED_AT),ID LIMIT %d',
            self::OPERATION_TABLE,
            $this->quote($this->date($now)),
            $limit,
        ));
        $ids = [];
        while (false !== ($row = $result->fetch())) {
            $ids[] = (string)$row['ID'];
        }

        return $ids;
    }

    private function complete(
        string $id,
        int $attempt,
        string $status,
        ?string $errorCode,
        ?\DateTimeImmutable $nextAttemptAt,
        \DateTimeImmutable $now,
    ): void {
        $this->transaction(function(Connection $connection) use ($id, $attempt, $status, $errorCode, $nextAttemptAt, $now): void {
            $timestamp = $this->date($now);
            $attemptStatus = 'accepted' === $status ? 'accepted' : 'rejected';
            $connection->queryExecute(sprintf(
                'UPDATE %s SET STATUS=%s,NEXT_ATTEMPT_AT=%s,PROCESSING_STARTED_AT=NULL,ACCEPTED_AT=%s,LAST_ERROR_CODE=%s,UPDATED_AT=%s '
                . "WHERE ID=%s AND STATUS='processing' AND ATTEMPTS=%d",
                self::OPERATION_TABLE,
                $this->quote($status),
                null === $nextAttemptAt ? 'NULL' : $this->quote($this->date($nextAttemptAt)),
                'accepted' === $status ? $this->quote($timestamp) : 'NULL',
                null === $errorCode ? 'NULL' : $this->quote($errorCode),
                $this->quote($timestamp),
                $this->quote($id),
                $attempt,
            ));
            $connection->queryExecute(sprintf(
                'UPDATE %s SET STATUS=%s,ERROR_CODE=%s,FINISHED_AT=%s WHERE OPERATION_ID=%s AND ATTEMPT_NO=%d AND STATUS=%s',
                self::ATTEMPT_TABLE,
                $this->quote($attemptStatus),
                null === $errorCode ? 'NULL' : $this->quote($errorCode),
                $this->quote($timestamp),
                $this->quote($id),
                $attempt,
                $this->quote('started'),
            ));
        });
    }

    private function markUnknownInTransaction(
        Connection $connection,
        string $id,
        int $attempt,
        string $errorCode,
        \DateTimeImmutable $now,
    ): void {
        $timestamp = $this->quote($this->date($now));
        $connection->queryExecute(sprintf(
            "UPDATE %s SET STATUS='unknown',NEXT_ATTEMPT_AT=NULL,PROCESSING_STARTED_AT=NULL,LAST_ERROR_CODE=%s,UPDATED_AT=%s "
            . "WHERE ID=%s AND STATUS='processing' AND ATTEMPTS=%d",
            self::OPERATION_TABLE,
            $this->quote($errorCode),
            $timestamp,
            $this->quote($id),
            $attempt,
        ));
        $connection->queryExecute(sprintf(
            "UPDATE %s SET STATUS='unknown',ERROR_CODE=%s,FINISHED_AT=%s WHERE OPERATION_ID=%s AND ATTEMPT_NO=%d AND STATUS='started'",
            self::ATTEMPT_TABLE,
            $this->quote($errorCode),
            $timestamp,
            $this->quote($id),
            $attempt,
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function dto(array $row): DeliveryOperationDto
    {
        return new DeliveryOperationDto(
            id: (string)$row['ID'],
            channel: (string)$row['CHANNEL'],
            recipient: (string)$row['RECIPIENT'],
            subject: (string)$row['SUBJECT'],
            body: (string)$row['BODY'],
            status: (string)$row['STATUS'],
            attempts: (int)$row['ATTEMPTS'],
            maxAttempts: (int)$row['MAX_ATTEMPTS'],
            nextAttemptAt: null === $row['NEXT_ATTEMPT_AT'] ? null : (string)$row['NEXT_ATTEMPT_AT'],
            acceptedAt: null === $row['ACCEPTED_AT'] ? null : (string)$row['ACCEPTED_AT'],
            bodyHtml: null === ($row['BODY_HTML'] ?? null) ? null : (string)$row['BODY_HTML'],
        );
    }

    private function quote(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    private function date(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * @template T
     *
     * @param callable(Connection): T $operation
     *
     * @return T
     */
    private function transaction(callable $operation): mixed
    {
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            $result = $operation($connection);
            $connection->commitTransaction();

            return $result;
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();
            throw $error;
        }
    }
}
