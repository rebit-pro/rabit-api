<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Access;

use Rebit\Auth\Application\Access\Contract\AccessLinkMailerInterface;
use Rebit\Auth\Application\Access\Dto\AccessLinkMailInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;

/** Letters with personal links through the durable H1 queue: a plain-text body plus an HTML version with a button. */
final readonly class H1AccessLinkMailer implements AccessLinkMailerInterface
{
    public function __construct(
        private EmailNotificationInterface $notifications,
        private string $appUrl,
        private string $brandName,
    ) {
        if (1 !== preg_match('~^https?://[^/\s]+~', $appUrl)) {
            throw new \InvalidArgumentException('Access link application URL is invalid.');
        }
    }

    public function sendInvitation(AccessLinkMailInputDto $mail): void
    {
        $link = $this->link('/access/invite/', $mail->token);
        $until = self::moscow($mail->expiresAt);
        $this->queue(
            consumer: 'auth-invite',
            key: 'invite:' . $mail->userId . ':' . $mail->issuedAt,
            mail: $mail,
            subject: sprintf('Приглашение в кабинет «%s»', $this->brandName),
            lead: sprintf('Вас пригласили в личный кабинет «%s». Задайте пароль, чтобы войти.', $this->brandName),
            action: 'Задать пароль',
            link: $link,
            note: sprintf('Ссылка действует до %s. Если вы не ждали приглашения, просто не отвечайте на это письмо.', $until),
        );
    }

    public function sendPasswordReset(AccessLinkMailInputDto $mail): void
    {
        $link = $this->link('/access/reset/', $mail->token);
        $until = self::moscow($mail->expiresAt);
        $this->queue(
            consumer: 'auth-reset',
            key: 'reset:' . $mail->userId . ':' . $mail->issuedAt,
            mail: $mail,
            subject: sprintf('Новый пароль для кабинета «%s»', $this->brandName),
            lead: 'Вы запросили новый пароль. Откройте ссылку и задайте его — старый пароль перестанет действовать.',
            action: 'Задать новый пароль',
            link: $link,
            note: sprintf('Ссылка одноразовая и действует до %s. Если вы не запрашивали новый пароль, ничего не делайте.', $until),
        );
    }

    private function queue(
        string $consumer,
        string $key,
        AccessLinkMailInputDto $mail,
        string $subject,
        string $lead,
        string $action,
        string $link,
        string $note,
    ): void {
        $greeting = '' !== trim($mail->name) ? sprintf('Здравствуйте, %s!', trim($mail->name)) : 'Здравствуйте!';
        $this->notifications->queue(new EmailNotificationInputDto(
            consumer: $consumer,
            deduplicationKey: $key,
            recipient: $mail->recipient,
            subject: $subject,
            body: implode("\n\n", [$greeting, $lead, $action . ': ' . $link, $note, $this->brandName]),
            bodyHtml: $this->html($greeting, $lead, $action, $link, $note),
        ));
    }

    private function html(string $greeting, string $lead, string $action, string $link, string $note): string
    {
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div style="margin:0;padding:32px 16px;background:#F5F8FA;font-family:Arial,sans-serif;color:#1E1E1E">'
            . '<div style="max-width:520px;margin:0 auto;padding:32px 28px;background:#FFFFFF;border:1px solid #DCE4EA;border-radius:16px">'
            . '<p style="margin:0 0 24px;font-size:20px;font-weight:600">' . $e($this->brandName) . '</p>'
            . '<p style="margin:0 0 12px;font-size:16px;line-height:24px">' . $e($greeting) . '</p>'
            . '<p style="margin:0 0 24px;font-size:16px;line-height:24px">' . $e($lead) . '</p>'
            . '<p style="margin:0 0 24px"><a href="' . $e($link) . '" style="display:inline-block;padding:12px 24px;border-radius:8px;'
            . 'background:#24658A;color:#FFFFFF;font-size:16px;font-weight:600;text-decoration:none">' . $e($action) . '</a></p>'
            . '<p style="margin:0 0 8px;font-size:13px;line-height:18px;color:#5E6872">' . $e($note) . '</p>'
            . '<p style="margin:0;font-size:13px;line-height:18px;color:#5E6872;word-break:break-all">' . $e($link) . '</p>'
            . '</div></div>';
    }

    private function link(string $path, string $token): string
    {
        return rtrim($this->appUrl, '/') . $path . rawurlencode($token);
    }

    private static function moscow(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone('Europe/Moscow'))
            ->format('d.m.Y H:i') . ' МСК'
        ;
    }
}
