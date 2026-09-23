<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Infrastructure\Lead;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Infrastructure\Lead\EmailLeadNotifier;
use Rebit\Share\Infrastructure\Logger\CommonLoggerProcessor;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class EmailLeadNotifierTest extends TestCase
{
    private string $attachmentPath = '';

    protected function setUp(): void
    {
        \CEvent::$lastSendImmediateCall = null;
        \CEvent::$sendImmediateResult = 'Y';
    }

    protected function tearDown(): void
    {
        if ('' !== $this->attachmentPath && is_file($this->attachmentPath)) {
            unlink($this->attachmentPath);
        }

        $this->attachmentPath = '';
    }

    public function testSendsLeadFields(): void
    {
        $this->notifier()->notify($this->lead());

        self::assertNotNull(\CEvent::$lastSendImmediateCall);
        self::assertSame('REBIT_NOTIFICATION_LEAD', \CEvent::$lastSendImmediateCall['eventName']);
        self::assertSame('s1', \CEvent::$lastSendImmediateCall['siteId']);

        $fields = \CEvent::$lastSendImmediateCall['fields'];

        self::assertSame('rebit@example.com', $fields['EMAIL_TO']);
        self::assertSame('Иван', $fields['NAME']);
        self::assertSame('+7 900 000-00-00', $fields['PHONE']);
        self::assertSame('ivan@example.com', $fields['EMAIL']);
        self::assertSame('https://rebit-pro.ru/', $fields['PAGE']);
        self::assertSame('', $fields['FILE_NAME']);
        self::assertSame([], \CEvent::$lastSendImmediateCall['filesContent']);
    }

    public function testUsesConfiguredEventName(): void
    {
        $notifier = new EmailLeadNotifier(
            new NullLogger(),
            'client@example.com',
            's1',
            'REBIT_NOTIFICATION_MOS_DIZEL_LEAD',
        );

        $notifier->notify($this->lead());

        self::assertSame('REBIT_NOTIFICATION_MOS_DIZEL_LEAD', \CEvent::$lastSendImmediateCall['eventName']);
        self::assertSame('client@example.com', \CEvent::$lastSendImmediateCall['fields']['EMAIL_TO']);
    }

    public function testEscapesUserInput(): void
    {
        $this->notifier()->notify(new LeadMessageDto(
            name: '<script>alert(1)</script>',
            phone: '+7 900 000-00-00',
            description: "Первая строка\nВторая строка",
            page: 'https://rebit-pro.ru/',
        ));

        self::assertNotNull(\CEvent::$lastSendImmediateCall);

        $fields = \CEvent::$lastSendImmediateCall['fields'];

        self::assertStringNotContainsString('<script>', $fields['NAME']);
        self::assertStringContainsString('<br', $fields['DESCRIPTION']);
    }

    public function testAttachesUploadedFile(): void
    {
        $this->attachmentPath = (string)tempnam(sys_get_temp_dir(), 'lead');
        file_put_contents($this->attachmentPath, 'содержимое ТЗ');

        $this->notifier()->notify($this->lead(), new LeadAttachmentDto(
            path: $this->attachmentPath,
            name: 'tz.pdf',
            mimeType: 'application/pdf',
            size: 13,
        ));

        self::assertNotNull(\CEvent::$lastSendImmediateCall);
        self::assertSame('tz.pdf', \CEvent::$lastSendImmediateCall['fields']['FILE_NAME']);

        $filesContent = \CEvent::$lastSendImmediateCall['filesContent'];

        self::assertCount(1, $filesContent);
        self::assertSame('tz.pdf', $filesContent[0]['NAME']);
        self::assertSame('application/pdf', $filesContent[0]['CONTENT_TYPE']);
        self::assertSame('содержимое ТЗ', $filesContent[0]['CONTENT']);
    }

    public function testUnreadableAttachmentDoesNotBlockDelivery(): void
    {
        $this->notifier()->notify($this->lead(), new LeadAttachmentDto(
            path: '/nonexistent/tz.pdf',
            name: 'tz.pdf',
            mimeType: 'application/pdf',
            size: 13,
        ));

        self::assertNotNull(\CEvent::$lastSendImmediateCall);
        self::assertSame([], \CEvent::$lastSendImmediateCall['filesContent']);
    }

    public function testEmptyRecipientThrows(): void
    {
        $notifier = new EmailLeadNotifier(new NullLogger(), '', 's1');

        $this->expectException(HttpException::class);

        $notifier->notify($this->lead());
    }

    public function testAcceptedLeadRecordSurvivesTheCommonLogSanitizer(): void
    {
        $handler = new TestHandler();

        (new EmailLeadNotifier(new Logger('notification', [$handler]), 'rebit@example.com', 's1'))->notify($this->lead());

        $record = $handler->getRecords()[0];
        $sanitized = (new CommonLoggerProcessor(['message' => $record['message'], 'context' => $record['context'], 'extra' => []]))();
        self::assertSame('Заявка передана почтовому транспорту', $sanitized['message']);
        self::assertSame(['event' => 'REBIT_NOTIFICATION_LEAD'], $sanitized['context']);
    }

    public function testFailedSendThrows(): void
    {
        \CEvent::$sendImmediateResult = false;

        $this->expectException(HttpException::class);

        $this->notifier()->notify($this->lead());
    }

    private function notifier(): EmailLeadNotifier
    {
        return new EmailLeadNotifier(new NullLogger(), 'rebit@example.com', 's1');
    }

    private function lead(): LeadMessageDto
    {
        return new LeadMessageDto(
            name: 'Иван',
            phone: '+7 900 000-00-00',
            description: 'Нужен сайт на Битриксе',
            page: 'https://rebit-pro.ru/',
            email: 'ivan@example.com',
        );
    }
}
