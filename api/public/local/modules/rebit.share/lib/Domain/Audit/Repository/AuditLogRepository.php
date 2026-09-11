<?php

declare(strict_types=1);

namespace Rebit\Share\Domain\Audit\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Rebit\Share\Shared\Exception\RepositoryException;
use Rebit\Share\Shared\Repository\RepositoryExceptionTrait;

final readonly class AuditLogRepository
{
    use RepositoryExceptionTrait;

    private const string TABLE_NAME = 'rebit_audit_log';

    /**
     * @param array<string, mixed> $payload
     *
     * @throws RepositoryException
     */
    public function create(
        int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        string $ipAddress,
        ?string $userAgent,
        array $payload,
    ): void {
        try {
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RepositoryException($exception->getMessage(), 0, $exception);
        }

        $this->query(function() use ($userId, $action, $entityType, $entityId, $ipAddress, $userAgent, $payloadJson): void {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $now = new DateTime();

            $entityTypeSql = null === $entityType || '' === $entityType
                ? 'NULL'
                : "'" . $helper->forSql($entityType) . "'";
            $entityIdSql = null === $entityId ? 'NULL' : (string)$entityId;
            $userAgentSql = null === $userAgent || '' === $userAgent
                ? 'NULL'
                : "'" . $helper->forSql($userAgent) . "'";

            $connection->queryExecute(
                sprintf(
                    'INSERT INTO %s (UF_USER_ID, UF_ACTION, UF_ENTITY_TYPE, UF_ENTITY_ID, UF_IP_ADDRESS, UF_USER_AGENT, UF_PAYLOAD, UF_CREATED_AT) VALUES (%d, \'%s\', %s, %s, \'%s\', %s, \'%s\', \'%s\')',
                    self::TABLE_NAME,
                    $userId,
                    $helper->forSql($action),
                    $entityTypeSql,
                    $entityIdSql,
                    $helper->forSql($ipAddress),
                    $userAgentSql,
                    $helper->forSql($payloadJson),
                    $helper->forSql($now->format('Y-m-d H:i:s')),
                ),
            );
        });
    }
}
