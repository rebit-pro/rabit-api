<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\Contract;

use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;

interface DeliveryOperationRepositoryInterface
{
    public function createOrGet(
        string $id,
        EmailNotificationInputDto $input,
        string $payloadHash,
        \DateTimeImmutable $now,
    ): DeliveryOperationDto;

    public function find(string $id): ?DeliveryOperationDto;

    public function startAttempt(
        string $id,
        \DateTimeImmutable $now,
        \DateTimeImmutable $staleBefore,
    ): ?DeliveryOperationDto;

    public function markAccepted(string $id, int $attempt, \DateTimeImmutable $now): void;

    public function markRejected(
        string $id,
        int $attempt,
        string $errorCode,
        ?\DateTimeImmutable $nextAttemptAt,
        \DateTimeImmutable $now,
    ): void;

    public function markUnknown(string $id, int $attempt, string $errorCode, \DateTimeImmutable $now): void;

    public function recoverStale(\DateTimeImmutable $staleBefore, \DateTimeImmutable $now): int;

    public function recoverUnknown(int $limit, \DateTimeImmutable $now): int;

    /**
     * @return list<string>
     */
    public function dueOperationIds(int $limit, \DateTimeImmutable $now): array;
}
