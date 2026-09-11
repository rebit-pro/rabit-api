<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Bitrix\Main\Type\DateTime;
use Rebit\Auth\Application\Auth\Contract\RegistrationConfirmationMailerInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class BitrixMailEventRegistrationConfirmationMailer implements RegistrationConfirmationMailerInterface
{
    private const string EVENT_NAME = 'REBIT_AUTH_REGISTRATION_CONFIRMATION';

    public function __construct(
        private string $siteId,
    ) {}

    /**
     * @throws HttpException
     */
    public function sendConfirmationCode(string $email, string $code, DateTime $expiresAt): void
    {
        $eventId = \CEvent::SendImmediate(
            self::EVENT_NAME,
            $this->siteId,
            [
                'EMAIL_TO' => $email,
                'CONFIRMATION_CODE' => $code,
                'EXPIRES_AT' => $expiresAt->format('d.m.Y H:i'),
            ],
        );

        if ('Y' !== $eventId) {
            throw new HttpException('Не удалось отправить письмо с кодом подтверждения.', 502);
        }
    }
}
