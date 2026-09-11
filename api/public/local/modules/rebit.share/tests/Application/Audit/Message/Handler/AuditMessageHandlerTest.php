<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Application\Audit\Message\Handler;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Audit\Message\AuditMessage;
use Rebit\Share\Application\Audit\Message\Handler\AuditMessageHandler;
use Rebit\Share\Domain\Audit\Repository\AuditLogRepository;

/**
 * @internal
 */
final class AuditMessageHandlerTest extends TestCase
{
    public function testMapsReservedFieldsAndUsesDefaultIpAddress(): void
    {
        $createCalled = 0;
        $auditLogRepository = $this->createStub(AuditLogRepository::class);
        $auditLogRepository->method('create')->willReturnCallback(
            function(
                int $userId,
                string $action,
                ?string $entityType,
                ?int $entityId,
                string $ipAddress,
                ?string $userAgent,
                array $payload,
            ) use (&$createCalled): void {
                ++$createCalled;

                if (15 !== $userId || 'file.uploaded' !== $action) {
                    throw new AssertionFailedError('Unexpected audit identity');
                }

                if ('file' !== $entityType || 99 !== $entityId) {
                    throw new AssertionFailedError('Unexpected entity mapping');
                }

                if ('0.0.0.0' !== $ipAddress || 'Mozilla/5.0' !== $userAgent) {
                    throw new AssertionFailedError('Unexpected transport metadata mapping');
                }

                if ([
                    'amount' => '100.50',
                    'side' => 'buy',
                ] !== $payload) {
                    throw new AssertionFailedError('Reserved keys should be removed from payload');
                }
            },
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $handler = new AuditMessageHandler($auditLogRepository, $logger);
        $handler(new AuditMessage(
            userId: 15,
            action: 'file.uploaded',
            context: [
                'entityType' => 'file',
                'entityId' => 99,
                'ipAddress' => '',
                'userAgent' => 'Mozilla/5.0',
                'amount' => '100.50',
                'side' => 'buy',
            ],
        ));

        self::assertSame(1, $createCalled);
    }
}
