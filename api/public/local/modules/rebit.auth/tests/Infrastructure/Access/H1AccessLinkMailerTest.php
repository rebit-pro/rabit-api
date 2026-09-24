<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Infrastructure\Access;

use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Access\Dto\AccessLinkMailInputDto;
use Rebit\Auth\Infrastructure\Access\H1AccessLinkMailer;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\NotificationOperationOutputDto;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;

/**
 * @internal
 */
final class H1AccessLinkMailerTest extends TestCase
{
    public function testInvitationLetterCarriesLinkAndEscapesTheName(): void
    {
        $queue = new RecordingNotifications();
        $mailer = new H1AccessLinkMailer($queue, 'https://app.example.invalid/', 'Море фото');

        $mailer->sendInvitation(new AccessLinkMailInputDto(
            userId: 7,
            recipient: 'anna@example.invalid',
            name: '<script>Анна</script>',
            token: 'abc_DEF-123',
            issuedAt: 1800000000,
            expiresAt: 1800604800,
        ));

        $input = $queue->inputs[0];
        self::assertSame('auth-invite', $input->consumer);
        self::assertSame('invite:7:1800000000', $input->deduplicationKey);
        self::assertSame('Приглашение в кабинет «Море фото»', $input->subject);
        self::assertStringContainsString('https://app.example.invalid/access/invite/abc_DEF-123', $input->body);
        $until = (new \DateTimeImmutable('@1800604800'))->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('d.m.Y H:i');
        self::assertStringContainsString('действует до ' . $until . ' МСК', $input->body);
        self::assertNotNull($input->bodyHtml);
        self::assertStringContainsString('href="https://app.example.invalid/access/invite/abc_DEF-123"', $input->bodyHtml);
        self::assertStringContainsString('&lt;script&gt;Анна&lt;/script&gt;', $input->bodyHtml);
        self::assertStringNotContainsString('<script>', $input->bodyHtml);
    }

    public function testResetLetterUsesItsOwnConsumerAndPath(): void
    {
        $queue = new RecordingNotifications();
        (new H1AccessLinkMailer($queue, 'https://app.example.invalid', 'Море фото'))->sendPasswordReset(new AccessLinkMailInputDto(
            userId: 8,
            recipient: 'boris@example.invalid',
            name: '',
            token: 'reset-token',
            issuedAt: 1800000000,
            expiresAt: 1800003600,
        ));

        self::assertSame('auth-reset', $queue->inputs[0]->consumer);
        self::assertSame('reset:8:1800000000', $queue->inputs[0]->deduplicationKey);
        self::assertStringStartsWith('Здравствуйте!', $queue->inputs[0]->body);
        self::assertStringContainsString('/access/reset/reset-token', $queue->inputs[0]->body);
    }

    public function testRejectsNonHttpApplicationUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new H1AccessLinkMailer(new RecordingNotifications(), 'javascript:alert(1)', 'Море фото');
    }
}

final class RecordingNotifications implements EmailNotificationInterface
{
    /** @var list<EmailNotificationInputDto> */
    public array $inputs = [];

    public function queue(EmailNotificationInputDto $input): NotificationOperationOutputDto
    {
        $this->inputs[] = $input;

        return new NotificationOperationOutputDto(id: '11111111-1111-4111-8111-111111111111', status: 'pending', attempts: 0, maxAttempts: 3);
    }

    public function status(string $operationId): ?NotificationOperationOutputDto
    {
        return null;
    }
}
