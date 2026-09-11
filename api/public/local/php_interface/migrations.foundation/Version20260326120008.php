<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260326120008 extends Version
{
    protected $author = 'copilot';

    protected $description = 'Создание таблицы подтверждения регистрации по e-mail';

    public function up(): void
    {
        $connection = Application::getConnection();

        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS rebit_auth_registration_confirmation (
    ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    UF_USER_ID INT UNSIGNED NOT NULL,
    UF_EMAIL VARCHAR(255) NOT NULL,
    UF_CODE_HASH VARCHAR(255) NOT NULL,
    UF_CODE_EXPIRES_AT DATETIME NOT NULL,
    UF_RESEND_AVAILABLE_AT DATETIME NOT NULL,
    UF_ATTEMPTS INT UNSIGNED NOT NULL DEFAULT 0,
    UF_CONFIRMED_AT DATETIME DEFAULT NULL,
    UF_CREATED_AT DATETIME NOT NULL,
    UF_UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_rebit_auth_registration_confirmation_email (UF_EMAIL),
    UNIQUE KEY ux_rebit_auth_registration_confirmation_user_id (UF_USER_ID),
    KEY ix_rebit_auth_registration_confirmation_expires_at (UF_CODE_EXPIRES_AT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Application::getConnection()->queryExecute(
            'DROP TABLE IF EXISTS rebit_auth_registration_confirmation',
        );
    }
}
