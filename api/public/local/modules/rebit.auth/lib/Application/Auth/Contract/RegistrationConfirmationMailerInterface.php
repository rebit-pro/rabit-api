<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Contract;

use Bitrix\Main\Type\DateTime;
use Rebit\Share\Shared\Exception\HttpException;

interface RegistrationConfirmationMailerInterface
{
    /**
     * @throws HttpException
     */
    public function sendConfirmationCode(string $email, string $code, DateTime $expiresAt): void;
}
