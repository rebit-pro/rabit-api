<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Audit\Message\Handler;

use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Audit\Message\AuditMessage;
use Rebit\Share\Domain\Audit\Repository\AuditLogRepository;

final readonly class AuditMessageHandler
{
    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AuditMessage $message): void
    {
        $entityType = isset($message->context['entityType']) ? (string)$message->context['entityType'] : null;
        $entityId = isset($message->context['entityId']) ? (int)$message->context['entityId'] : null;
        $ipAddress = isset($message->context['ipAddress']) && '' !== (string)$message->context['ipAddress']
            ? (string)$message->context['ipAddress']
            : '0.0.0.0';
        $userAgent = isset($message->context['userAgent']) && '' !== (string)$message->context['userAgent']
            ? (string)$message->context['userAgent']
            : null;

        $payload = $message->context;
        unset($payload['entityType'], $payload['entityId'], $payload['ipAddress'], $payload['userAgent']);

        $this->auditLogRepository->create(
            userId: $message->userId,
            action: $message->action,
            entityType: $entityType,
            entityId: $entityId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            payload: $payload,
        );

        $this->logger->info('AuditMessage получено', [
            'userId' => $message->userId,
            'action' => $message->action,
            'context' => $message->context,
        ]);
    }
}
