<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Bitrix\Event;

use Bitrix\Main\Application;

/** Registered persistently so Bitrix administration also revokes pending state. */
final readonly class RegistrationStateHandler
{
    /** @param array<string, mixed> $fields */
    public static function onBeforeUserUpdate(array &$fields): void
    {
        if ('N' === ($fields['ACTIVE'] ?? null)) {
            // CUser writes b_user and its UF in separate autocommit statements.
            // Revoke BEFORE that window, so confirm cannot observe N + pending=1.
            $userId = (int)($fields['ID'] ?? 0);
            if (0 >= $userId) {
                throw new \LogicException('Administrative disable requires a valid user ID.');
            }
            Application::getConnection()->queryExecute(sprintf(
                "UPDATE b_uts_user SET UF_AUTH_REGISTRATION_PENDING = 0, UF_TOKEN = '', UF_TOKEN_EXPIRES_AT = NULL WHERE VALUE_ID = %d",
                $userId,
            ));
            $fields['UF_AUTH_REGISTRATION_PENDING'] = 0;
            $fields['UF_TOKEN'] = '';
            $fields['UF_TOKEN_EXPIRES_AT'] = '';
        } elseif ('Y' === ($fields['ACTIVE'] ?? null)) {
            // Explicit administrative activation and confirmation both finish pending.
            $fields['UF_AUTH_REGISTRATION_PENDING'] = 0;
        }
    }
}
