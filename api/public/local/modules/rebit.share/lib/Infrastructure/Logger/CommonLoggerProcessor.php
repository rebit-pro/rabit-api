<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Logger;

/** Metadata and the same redaction policy apply to every configured log sink. */
final readonly class CommonLoggerProcessor
{
    /** @param array<string, mixed> $record Monolog 2 record. */
    public function __construct(
        private array $record,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        $record = $this->record;
        $sanitizer = new LogSanitizer();
        $record['message'] = $sanitizer->message($record['message']);
        $record['context'] = $sanitizer->context($record['context']);
        $extra = $sanitizer->context($record['extra']);
        $extra['requestId'] = RequestIdGenerator::getRequestId();
        $extra['durationMs'] = RequestIdGenerator::getDurationMs();
        $extra['method'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $record['extra'] = $sanitizer->context($extra);

        return $record;
    }
}
