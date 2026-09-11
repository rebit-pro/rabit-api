<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Logger;

use Bitrix\Main\Application;

/**
 * Общий процессор для логов, автоматически добавляет в контекст запроса информацию о запросе.
 *
 *  @phpstan-type ALoggerRecord array{
 *     message: string,
 *     context: mixed[],
 *     level: Logger::DEBUG|Logger::INFO|Logger::NOTICE|Logger::WARNING|Logger::ERROR|Logger::CRITICAL|Logger::ALERT|Logger::EMERGENCY,
 *     level_name: string,
 *     channel: string,
 *     datetime: \DateTimeImmutable,
 *     extra: mixed[]
 * }
 */
final class CommonLoggerProcessor
{
    /**
     * @param ALoggerRecord $record
     */
    public function __construct(
        private array $record,
    ) {}

    /**
     * ```
     * ALoggerRecord['extra'] + array {
     *     method: string,
     *     uri: string,
     *     ip: string,
     *     userAgent: string,
     *     requestId: string,
     * }
     * ```
     *
     * @return ALoggerRecord
     */
    public function __invoke(): array
    {
        $request = Application::getInstance()->getContext()->getRequest();

        $this->record['extra']['method'] = $request->getRequestMethod();
        $this->record['extra']['uri'] = $request->getRequestUri();
        $this->record['extra']['ip'] = $request->getServer()->getRemoteAddr();
        $this->record['extra']['userAgent'] = $request->getServer()->getUserAgent();
        $this->record['extra']['requestId'] = RequestIdGenerator::getRequestId();

        return $this->record;
    }
}
