<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Logger;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Logger\CommonLoggerProcessor;
use Rebit\Share\Infrastructure\Logger\LogSanitizer;

/**
 * @internal
 */
final class DiagnosticLogSanitizerTest extends TestCase
{
    private const string PHOTO_ID = '12345678-abcd-4abc-8abc-123456789abc';

    public function testMediaDiagnosticsKeepTypedMetadata(): void
    {
        $context = [
            'photoId' => self::PHOTO_ID,
            'photoStatus' => 'duplicate',
            'bytes' => 8_388_608,
            'published' => false,
            'stage' => 'markPublished',
            'exception' => \RuntimeException::class,
            'previous' => 'Symfony\Component\Messenger\Exception\TransportException',
            'revision' => 3,
            'attempt' => 2,
            'inspectMs' => 12,
            'storeMs' => 340,
            'registerMs' => 4,
            'publishMs' => 0,
            'decodeMs' => 900,
            'thumbMs' => 40,
            'previewMs' => 120,
            'sinceAcceptedSeconds' => 58,
            'sinceQueuedSeconds' => 57,
            'pendingSeconds' => 61,
            'megapixels' => 24.04,
        ];

        $record = $this->process('Photo upload accepted.', $context);
        $expected = ['megapixels' => 24.0] + $context;
        $actual = $record['context'];
        ksort($expected);
        ksort($actual);

        self::assertSame('Photo upload accepted.', $record['message']);
        self::assertSame($expected, $actual);
    }

    public function testInvalidDiagnosticValuesAreDroppedAndMarked(): void
    {
        $safe = (new LogSanitizer())->context([
            'photoId' => 'photo-1',
            'operationId' => 'SELECT 1',
            'photoStatus' => 'deleted',
            'published' => 'yes',
            'storeMs' => -1,
            'thumbMs' => 40.5,
            'attempt' => '2',
            'megapixels' => INF,
            'exception' => new \RuntimeException('secret message'),
            'stage' => 'publish failed with secret',
            'event' => 'REBIT_NOTIFICATION_LEAD',
            'payload' => ['token' => 'secret'],
        ]);

        self::assertSame(['event' => 'REBIT_NOTIFICATION_LEAD', 'redacted' => true], $safe);
    }

    public function testAllowedKeyWithoutValueIsNotReportedAsRedacted(): void
    {
        self::assertSame(
            ['photoId' => self::PHOTO_ID, 'exception' => \LogicException::class],
            (new LogSanitizer())->context(['photoId' => self::PHOTO_ID, 'exception' => \LogicException::class, 'previous' => null]),
        );
    }

    public function testDeliveryMessagesAreKnownAndUnknownTextStaysRedacted(): void
    {
        $sanitizer = new LogSanitizer();

        foreach ([
            'Pending photo job dispatched.',
            'Pending photo job was not dispatched.',
            'Photo job remains pending after immediate publish failure.',
            'Photo preview preparation failed.',
            'Photo previews ready.',
            'Notification operation remains pending after publish failure.',
            'Notification operation remains pending after recovery publish failure.',
            'Заявка передана почтовому транспорту',
        ] as $message) {
            self::assertSame($message, $sanitizer->message($message));
        }
        self::assertSame(LogSanitizer::REDACTED, $sanitizer->message('Photo upload accepted. photo.jpg'));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function process(string $message, array $context): array
    {
        return (new CommonLoggerProcessor(['message' => $message, 'context' => $context, 'extra' => []]))();
    }
}
