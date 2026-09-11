<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\Service;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Infrastructure\Adapter\BitrixMailEventRegistrationConfirmationMailer;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class BitrixMailEventRegistrationConfirmationMailerTest extends TestCase
{
    protected function setUp(): void
    {
        \CEvent::$lastSendImmediateCall = null;
        \CEvent::$sendImmediateResult = 'Y';
    }

    public function testSendsBitrixMailEventWithExpectedFields(): void
    {
        $mailer = new BitrixMailEventRegistrationConfirmationMailer('s1');
        $expiresAt = DateTime::createFromTimestamp(1760000000);

        $mailer->sendConfirmationCode('user@example.com', '123456', $expiresAt);

        self::assertNotNull(\CEvent::$lastSendImmediateCall);
        self::assertSame('REBIT_AUTH_REGISTRATION_CONFIRMATION', \CEvent::$lastSendImmediateCall['eventName']);
        self::assertSame('s1', \CEvent::$lastSendImmediateCall['siteId']);
        self::assertSame('user@example.com', \CEvent::$lastSendImmediateCall['fields']['EMAIL_TO']);
        self::assertSame('123456', \CEvent::$lastSendImmediateCall['fields']['CONFIRMATION_CODE']);
        self::assertSame($expiresAt->format('d.m.Y H:i'), \CEvent::$lastSendImmediateCall['fields']['EXPIRES_AT']);
    }

    public function testThrowsHttpExceptionWhenBitrixMailEventSendFails(): void
    {
        \CEvent::$sendImmediateResult = false;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Не удалось отправить письмо с кодом подтверждения.');
        $this->expectExceptionCode(502);

        (new BitrixMailEventRegistrationConfirmationMailer('s1'))->sendConfirmationCode(
            'user@example.com',
            '123456',
            new DateTime(),
        );
    }
}
