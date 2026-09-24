<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Unit\Delivery;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Notification\Application\Delivery\Contract\DeliveryOperationRepositoryInterface;
use Rebit\Notification\Application\Delivery\Contract\EmailTransportInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationClockInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationPublisherInterface;
use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;
use Rebit\Notification\Application\Delivery\Exception\DefiniteDeliveryException;
use Rebit\Notification\Application\Delivery\UseCase\DeliverEmailUseCase;
use Rebit\Notification\Application\Delivery\UseCase\DispatchPendingEmailUseCase;
use Rebit\Notification\Application\Delivery\UseCase\QueueEmailUseCase;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\NotificationDeduplicationConflictException;
use Rebit\Share\Infrastructure\Logger\CommonLoggerProcessor;

/**
 * @internal
 */
final class DeliveryUseCasesTest extends TestCase
{
    public function testQueueIsDurableAndDeduplicatedWhenPublishFails(): void
    {
        $repository = new InMemoryDeliveryRepository();
        $publisher = new RecordingPublisher();
        $publisher->fail = true;
        $useCase = new QueueEmailUseCase($repository, $publisher, new FixedNotificationClock(), new NullLogger());

        $first = $useCase->queue($this->input());
        $second = $useCase->queue($this->input());

        self::assertSame($first->id, $second->id);
        self::assertSame('pending', $first->status);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/D', $first->id);
        self::assertSame(2, $publisher->calls);
        self::assertEquals($first, $useCase->status($first->id));
    }

    public function testDeduplicationKeyRejectsAnotherPayload(): void
    {
        $useCase = new QueueEmailUseCase(
            new InMemoryDeliveryRepository(),
            new RecordingPublisher(),
            new FixedNotificationClock(),
            new NullLogger(),
        );
        $useCase->queue($this->input());

        $this->expectException(NotificationDeduplicationConflictException::class);

        $useCase->queue(new EmailNotificationInputDto(
            consumer: 'payments.receipt',
            deduplicationKey: 'order-42',
            recipient: 'buyer@example.test',
            subject: 'Другой чек',
            body: 'Изменённое тело',
        ));
    }

    public function testPlainTextHashIsUnchangedAndHtmlJoinsTheHash(): void
    {
        $plain = new InMemoryDeliveryRepository();
        (new QueueEmailUseCase($plain, new RecordingPublisher(), new FixedNotificationClock(), new NullLogger()))->queue($this->input());
        $input = $this->input();
        self::assertSame(
            hash('sha256', implode("\0", ['email', 'buyer@example.test', $input->subject, $input->body, '3'])),
            $plain->payloadHash,
        );

        $html = new InMemoryDeliveryRepository();
        $useCase = new QueueEmailUseCase($html, new RecordingPublisher(), new FixedNotificationClock(), new NullLogger());
        $operation = $useCase->queue(new EmailNotificationInputDto(
            consumer: $input->consumer,
            deduplicationKey: $input->deduplicationKey,
            recipient: $input->recipient,
            subject: $input->subject,
            body: $input->body,
            bodyHtml: '<p>Чек</p>',
        ));
        self::assertNotSame($plain->payloadHash, $html->payloadHash);
        self::assertSame('<p>Чек</p>', $html->operation?->bodyHtml);
        self::assertSame('pending', $operation->status);

        $this->expectException(NotificationDeduplicationConflictException::class);
        $useCase->queue($input);
    }

    public function testAcceptedMeansTransportAcceptedNotRecipientDelivered(): void
    {
        $repository = $this->pendingRepository();
        $useCase = new DeliverEmailUseCase($repository, new RecordingEmailTransport(), new FixedNotificationClock());

        $useCase->execute($repository->operation->id);

        self::assertSame('accepted', $repository->operation->status);
        self::assertSame(1, $repository->operation->attempts);
        self::assertNotNull($repository->operation->acceptedAt);
    }

    public function testDefiniteFailureRetriesWithLimit(): void
    {
        $repository = $this->pendingRepository();
        $transport = new RecordingEmailTransport();
        $transport->mode = 'rejected';
        $useCase = new DeliverEmailUseCase($repository, $transport, new FixedNotificationClock());

        $useCase->execute($repository->operation->id);

        self::assertSame('retryWait', $repository->operation->status);
        self::assertNotNull($repository->operation->nextAttemptAt);
        $repository->operation = new DeliveryOperationDto(
            id: $repository->operation->id,
            channel: 'email',
            recipient: 'buyer@example.test',
            subject: 'Чек',
            body: 'Тело',
            status: 'pending',
            attempts: 2,
            maxAttempts: 3,
        );
        $useCase->execute($repository->operation->id);

        self::assertSame('failed', $repository->operation->status);
        self::assertSame(3, $repository->operation->attempts);
    }

    public function testUnknownOutcomeRequiresExplicitRecovery(): void
    {
        $repository = $this->pendingRepository();
        $transport = new RecordingEmailTransport();
        $transport->mode = 'unknown';
        (new DeliverEmailUseCase($repository, $transport, new FixedNotificationClock()))
            ->execute($repository->operation->id)
        ;

        self::assertSame('unknown', $repository->operation->status);

        $publisher = new RecordingPublisher();
        $dispatch = new DispatchPendingEmailUseCase(
            $repository,
            $publisher,
            new FixedNotificationClock(),
            new NullLogger(),
        );
        self::assertSame(0, $dispatch->execute(100));
        self::assertSame(0, $publisher->calls);
        self::assertSame(1, $dispatch->execute(100, true));
        self::assertSame(1, $publisher->calls);
        self::assertSame('retryWait', $repository->operation->status);
    }

    public function testUnsupportedStoredChannelFailsWithoutTransportCall(): void
    {
        $repository = $this->pendingRepository('max');
        $transport = new RecordingEmailTransport();

        (new DeliverEmailUseCase($repository, $transport, new FixedNotificationClock()))
            ->execute($repository->operation->id)
        ;

        self::assertSame('failed', $repository->operation->status);
        self::assertSame(0, $transport->calls);
    }

    public function testPublishFailureRecordsSurviveTheCommonLogSanitizer(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('notification', [$handler]);
        $publisher = new RecordingPublisher();
        $publisher->fail = true;

        (new QueueEmailUseCase(new InMemoryDeliveryRepository(), $publisher, new FixedNotificationClock(), $logger))->queue($this->input());
        (new DispatchPendingEmailUseCase($this->pendingRepository(), $publisher, new FixedNotificationClock(), $logger))->execute(100);

        self::assertSame([
            'Notification operation remains pending after publish failure.',
            'Notification operation remains pending after recovery publish failure.',
        ], array_column($handler->getRecords(), 'message'));
        foreach ($handler->getRecords() as $record) {
            $sanitized = (new CommonLoggerProcessor(['message' => $record['message'], 'context' => $record['context'], 'extra' => []]))();
            $actual = $sanitized['context'];
            ksort($actual);

            self::assertSame($record['message'], $sanitized['message']);
            self::assertSame(['exception' => \RuntimeException::class, 'operationId' => $record['context']['operationId']], $actual);
            self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/D', $actual['operationId']);
        }
    }

    private function input(): EmailNotificationInputDto
    {
        return new EmailNotificationInputDto(
            consumer: 'payments.receipt',
            deduplicationKey: 'order-42',
            recipient: 'buyer@example.test',
            subject: 'Чек',
            body: 'Тело письма',
        );
    }

    private function pendingRepository(string $channel = 'email'): InMemoryDeliveryRepository
    {
        $repository = new InMemoryDeliveryRepository();
        $repository->operation = new DeliveryOperationDto(
            id: '11111111-1111-4111-8111-111111111111',
            channel: $channel,
            recipient: 'buyer@example.test',
            subject: 'Чек',
            body: 'Тело',
            status: 'pending',
            attempts: 0,
            maxAttempts: 3,
        );

        return $repository;
    }
}

final class FixedNotificationClock implements NotificationClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-20 12:00:00', new \DateTimeZone('UTC'));
    }
}

final class RecordingPublisher implements NotificationPublisherInterface
{
    public int $calls = 0;
    public bool $fail = false;

    public function publish(string $operationId): void
    {
        ++$this->calls;
        if ($this->fail) {
            throw new \RuntimeException('RabbitMQ unavailable.');
        }
    }
}

final class RecordingEmailTransport implements EmailTransportInterface
{
    public int $calls = 0;
    public string $mode = 'accepted';

    public function send(DeliveryOperationDto $operation): void
    {
        ++$this->calls;
        if ('rejected' === $this->mode) {
            throw new DefiniteDeliveryException('smtp_rejected');
        }
        if ('unknown' === $this->mode) {
            throw new \RuntimeException('timeout');
        }
    }
}

final class InMemoryDeliveryRepository implements DeliveryOperationRepositoryInterface
{
    public ?DeliveryOperationDto $operation = null;
    public ?string $payloadHash = null;

    public function createOrGet(
        string $id,
        EmailNotificationInputDto $input,
        string $payloadHash,
        \DateTimeImmutable $now,
    ): DeliveryOperationDto {
        if (null !== $this->operation) {
            if ($payloadHash !== $this->payloadHash) {
                throw new NotificationDeduplicationConflictException();
            }

            return $this->operation;
        }
        $this->payloadHash = $payloadHash;
        $this->operation = new DeliveryOperationDto(
            id: $id,
            channel: 'email',
            recipient: $input->recipient,
            subject: $input->subject,
            body: $input->body,
            status: 'pending',
            attempts: 0,
            maxAttempts: $input->maxAttempts,
            bodyHtml: $input->bodyHtml,
        );

        return $this->operation;
    }

    public function find(string $id): ?DeliveryOperationDto
    {
        return $this->operation?->id === $id ? $this->operation : null;
    }

    public function startAttempt(
        string $id,
        \DateTimeImmutable $now,
        \DateTimeImmutable $staleBefore,
    ): ?DeliveryOperationDto {
        if (null === $this->operation || $this->operation->id !== $id
            || !in_array($this->operation->status, ['pending', 'retryWait'], true)) {
            return null;
        }
        $this->operation = $this->copy(
            status: 'processing',
            attempts: $this->operation->attempts + 1,
            nextAttemptAt: null,
        );

        return $this->operation;
    }

    public function markAccepted(string $id, int $attempt, \DateTimeImmutable $now): void
    {
        $this->operation = $this->copy(status: 'accepted', acceptedAt: $now->format('Y-m-d H:i:s'));
    }

    public function markRejected(
        string $id,
        int $attempt,
        string $errorCode,
        ?\DateTimeImmutable $nextAttemptAt,
        \DateTimeImmutable $now,
    ): void {
        $this->operation = $this->copy(
            status: null === $nextAttemptAt ? 'failed' : 'retryWait',
            nextAttemptAt: $nextAttemptAt?->format('Y-m-d H:i:s'),
        );
    }

    public function markUnknown(string $id, int $attempt, string $errorCode, \DateTimeImmutable $now): void
    {
        $this->operation = $this->copy(status: 'unknown');
    }

    public function recoverStale(\DateTimeImmutable $staleBefore, \DateTimeImmutable $now): int
    {
        return 0;
    }

    public function recoverUnknown(int $limit, \DateTimeImmutable $now): int
    {
        if (null === $this->operation || 'unknown' !== $this->operation->status
            || $this->operation->attempts >= $this->operation->maxAttempts) {
            return 0;
        }
        $this->operation = $this->copy(status: 'retryWait', nextAttemptAt: $now->format('Y-m-d H:i:s'));

        return 1;
    }

    public function dueOperationIds(int $limit, \DateTimeImmutable $now): array
    {
        if (null === $this->operation || !in_array($this->operation->status, ['pending', 'retryWait'], true)) {
            return [];
        }

        return [$this->operation->id];
    }

    private function copy(
        string $status,
        ?int $attempts = null,
        ?string $nextAttemptAt = null,
        ?string $acceptedAt = null,
    ): DeliveryOperationDto {
        if (null === $this->operation) {
            throw new \LogicException('Operation is missing.');
        }

        return new DeliveryOperationDto(
            id: $this->operation->id,
            channel: $this->operation->channel,
            recipient: $this->operation->recipient,
            subject: $this->operation->subject,
            body: $this->operation->body,
            status: $status,
            attempts: $attempts ?? $this->operation->attempts,
            maxAttempts: $this->operation->maxAttempts,
            nextAttemptAt: $nextAttemptAt,
            acceptedAt: $acceptedAt,
        );
    }
}
