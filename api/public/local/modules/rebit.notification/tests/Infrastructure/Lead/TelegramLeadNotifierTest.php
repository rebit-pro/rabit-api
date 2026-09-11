<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Infrastructure\Lead;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Infrastructure\Lead\TelegramLeadNotifier;
use Rebit\Share\Infrastructure\Telegram\TelegramBotApiClient;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class TelegramLeadNotifierTest extends TestCase
{
    public function testSendsEscapedLeadText(): void
    {
        $client = $this->createMock(TelegramBotApiClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects(self::once())
            ->method('call')
            ->with(
                'sendMessage',
                self::callback(static function(string $body): bool {
                    parse_str($body, $fields);
                    self::assertSame('42', $fields['chat_id']);
                    self::assertSame('HTML', $fields['parse_mode']);
                    self::assertStringContainsString('&lt;Иван&gt;', $fields['text']);
                    self::assertStringContainsString('Каталог &amp; корзина', $fields['text']);
                    self::assertStringContainsString('ivan@example.com', $fields['text']);
                    self::assertStringContainsString('https://example.com/estimate', $fields['text']);
                    self::assertStringNotContainsString('<Иван>', $fields['text']);

                    return true;
                }),
                12,
            )
            ->willReturn(true)
        ;

        new TelegramLeadNotifier(new NullLogger(), $client, '42')->notify($this->lead());
    }

    public function testUnconfiguredClientDoesNotSend(): void
    {
        $client = $this->createMock(TelegramBotApiClient::class);
        $client->method('isConfigured')->willReturn(false);
        $client->expects(self::never())->method('call');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(503);

        new TelegramLeadNotifier(new NullLogger(), $client, '42')->notify($this->lead());
    }

    public function testFailedLeadTextThrowsAndSkipsAttachment(): void
    {
        $client = $this->createMock(TelegramBotApiClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects(self::once())
            ->method('call')
            ->with('sendMessage', self::isString(), 12)
            ->willReturn(false)
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(502);

        new TelegramLeadNotifier(new NullLogger(), $client, '42')->notify($this->lead(), $this->attachment());
    }

    public function testFailedAttachmentWarnsWithoutRejectingDeliveredLead(): void
    {
        $attachment = $this->attachment();
        $calls = 0;
        $client = $this->createMock(TelegramBotApiClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->expects(self::exactly(3))
            ->method('call')
            ->willReturnCallback(static function(string $method, array|string $body, int $timeout) use (&$calls, $attachment): bool {
                ++$calls;

                if (2 === $calls) {
                    self::assertSame('sendDocument', $method);
                    self::assertSame(60, $timeout);
                    self::assertIsArray($body);
                    self::assertSame('42', $body['chat_id']);
                    self::assertInstanceOf(\CURLFile::class, $body['document']);
                    self::assertSame($attachment->path, $body['document']->getFilename());
                    self::assertSame($attachment->name, $body['document']->getPostFilename());

                    return false;
                }

                self::assertSame('sendMessage', $method);
                self::assertSame(12, $timeout);
                self::assertIsString($body);

                if (3 === $calls) {
                    parse_str($body, $fields);
                    self::assertStringContainsString('доставить не удалось', $fields['text']);
                }

                return true;
            })
        ;

        new TelegramLeadNotifier(new NullLogger(), $client, '42')->notify($this->lead(), $attachment);
    }

    private function lead(): LeadMessageDto
    {
        return new LeadMessageDto(
            name: '<Иван>',
            phone: '+7 900 000-00-00',
            description: 'Каталог & корзина',
            page: 'https://example.com/estimate',
            email: 'ivan@example.com',
        );
    }

    private function attachment(): LeadAttachmentDto
    {
        return new LeadAttachmentDto(
            path: __FILE__,
            name: 'brief.txt',
            mimeType: 'text/plain',
            size: 12,
        );
    }
}
