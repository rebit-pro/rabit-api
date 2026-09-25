<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;
use Rebit\Notification\Application\Delivery\Exception\DefiniteDeliveryException;
use Rebit\Notification\Infrastructure\Email\BitrixEmailTransport;

/**
 * @internal
 */
final class BitrixEmailTransportTest extends TestCase
{
    protected function setUp(): void
    {
        \CEvent::$lastSendImmediateCall = null;
        \CEvent::$sendImmediateResult = 'Y';
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
