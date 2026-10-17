<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Unit\Delivery;

use Bitrix\Main\Config\Option;
use PHPUnit\Framework\TestCase;
use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;
use Rebit\Notification\Application\Delivery\Exception\DefiniteDeliveryException;
use Rebit\Notification\Infrastructure\Email\BitrixEmailTransport;
use Rebit\Share\Application\Contract\Notification\Dto\EmailAttachmentInputDto;

/**
 * @internal
 */
final class BitrixEmailTransportTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        \CEvent::$lastSendImmediateCall = null;
        \CEvent::$sendImmediateResult = 'Y';
        Option::$values = [];
        $this->directory = sys_get_temp_dir() . '/rebit-transport-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);
    }

    public function testAttachmentsAreReadFromTheReferencedFilesAtSendTime(): void
    {
        file_put_contents($this->directory . '/a.jpg', "\xFF\xD8 first \x00 original");
        file_put_contents($this->directory . '/b.png', 'second');
        Option::$values['main:max_file_size'] = '0';

        (new BitrixEmailTransport('s1'))->send($this->withAttachments([
            new EmailAttachmentInputDto('AB001.jpg', 'image/jpeg', $this->directory . '/a.jpg', 19),
            new EmailAttachmentInputDto('AB002.png', 'image/png', $this->directory . '/b.png', 6),
        ]));

        self::assertSame([
            ['CONTENT_TYPE' => 'image/jpeg', 'NAME' => 'AB001.jpg', 'CONTENT' => "\xFF\xD8 first \x00 original", 'ID' => 'attachment-1', 'CHARSET' => null, 'METHOD' => null],
            ['CONTENT_TYPE' => 'image/png', 'NAME' => 'AB002.png', 'CONTENT' => 'second', 'ID' => 'attachment-2', 'CHARSET' => null, 'METHOD' => null],
        ], \CEvent::$lastSendImmediateCall['filesContent'] ?? null);
    }

    public function testAMissingOrChangedFileIsADefiniteFailure(): void
    {
        file_put_contents($this->directory . '/a.jpg', 'original');
        foreach ([[$this->directory . '/a.jpg', 9], [$this->directory . '/gone.jpg', 8]] as [$path, $bytes]) {
            try {
                (new BitrixEmailTransport('s1'))->send($this->withAttachments([new EmailAttachmentInputDto('AB001.jpg', 'image/jpeg', $path, $bytes)]));
                self::fail('The letter must not go without its attachment.');
            } catch (DefiniteDeliveryException $error) {
                self::assertSame('attachment_unavailable', $error->errorCode);
            }
        }
        self::assertNull(\CEvent::$lastSendImmediateCall);
    }

    public function testBitrixFileLimitBelowTheLetterIsRefusedInsteadOfATextStub(): void
    {
        file_put_contents($this->directory . '/a.jpg', str_repeat('x', 300));
        Option::$values['main:max_file_size'] = '399';

        try {
            (new BitrixEmailTransport('s1'))->send($this->withAttachments([new EmailAttachmentInputDto('AB001.jpg', 'image/jpeg', $this->directory . '/a.jpg', 300)]));
            self::fail('Bitrix would replace the attachment with a text stub.');
        } catch (DefiniteDeliveryException $error) {
            self::assertSame('attachment_limit', $error->errorCode);
        }
        Option::$values['main:max_file_size'] = '400';
        (new BitrixEmailTransport('s1'))->send($this->withAttachments([new EmailAttachmentInputDto('AB001.jpg', 'image/jpeg', $this->directory . '/a.jpg', 300)]));
        self::assertCount(1, \CEvent::$lastSendImmediateCall['filesContent'] ?? []);
    }

    public function testSendsEscapedPlainTextThroughDedicatedEvent(): void
    {
        (new BitrixEmailTransport('s1'))->send($this->operation());

        self::assertNotNull(\CEvent::$lastSendImmediateCall);
        self::assertSame('REBIT_NOTIFICATION_OUTGOING_EMAIL', \CEvent::$lastSendImmediateCall['eventName']);
        self::assertSame('buyer@example.test', \CEvent::$lastSendImmediateCall['fields']['EMAIL_TO']);
        self::assertSame('Чек &lt;42&gt;', \CEvent::$lastSendImmediateCall['fields']['SUBJECT']);
        self::assertStringContainsString('&lt;script&gt;', \CEvent::$lastSendImmediateCall['fields']['BODY']);
        self::assertStringContainsString('<br', \CEvent::$lastSendImmediateCall['fields']['BODY']);
    }

    public function testSendsSenderHtmlAsIs(): void
    {
        $operation = $this->operation();
        (new BitrixEmailTransport('s1'))->send(new DeliveryOperationDto(
            id: $operation->id,
            channel: $operation->channel,
            recipient: $operation->recipient,
            subject: $operation->subject,
            body: $operation->body,
            status: $operation->status,
            attempts: $operation->attempts,
            maxAttempts: $operation->maxAttempts,
            bodyHtml: '<p>Здравствуйте, <b>Анна</b></p>',
        ));

        self::assertNotNull(\CEvent::$lastSendImmediateCall);
        self::assertSame('<p>Здравствуйте, <b>Анна</b></p>', \CEvent::$lastSendImmediateCall['fields']['BODY']);
    }

    public function testRejectedEventIsDefiniteFailure(): void
    {
        \CEvent::$sendImmediateResult = false;

        $this->expectException(DefiniteDeliveryException::class);

        (new BitrixEmailTransport('s1'))->send($this->operation());
    }

    /** @param list<EmailAttachmentInputDto> $attachments */
    private function withAttachments(array $attachments): DeliveryOperationDto
    {
        $operation = $this->operation();

        return new DeliveryOperationDto(
            id: $operation->id,
            channel: $operation->channel,
            recipient: $operation->recipient,
            subject: $operation->subject,
            body: $operation->body,
            status: $operation->status,
            attempts: $operation->attempts,
            maxAttempts: $operation->maxAttempts,
            attachments: $attachments,
        );
    }

    private function operation(): DeliveryOperationDto
    {
        return new DeliveryOperationDto(
            id: '11111111-1111-4111-8111-111111111111',
            channel: 'email',
            recipient: 'buyer@example.test',
            subject: 'Чек <42>',
            body: "<script>alert(1)</script>\nГотов",
            status: 'processing',
            attempts: 1,
            maxAttempts: 3,
        );
    }
}
