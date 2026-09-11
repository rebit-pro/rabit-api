<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

final class Version20260713120001 extends Version
{
    protected $author = 'claude';

    protected $description = 'Регистрация модуля rebit.leadhunter и таблица внешних заявок';

    public function up(): void
    {
        if (!ModuleManager::isModuleInstalled('rebit.leadhunter')) {
            ModuleManager::registerModule('rebit.leadhunter');
        }

        Application::getConnection()->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS rebit_leadhunter_external_lead (
    ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    UF_SOURCE VARCHAR(32) NOT NULL,
    UF_GUID VARCHAR(255) NOT NULL,
    UF_TITLE VARCHAR(500) NOT NULL,
    UF_DESCRIPTION TEXT NOT NULL,
    UF_URL VARCHAR(500) NOT NULL,
    UF_MATCHED_KEYWORDS VARCHAR(500) NOT NULL DEFAULT '',
    UF_STATUS VARCHAR(16) NOT NULL,
    UF_ATTEMPTS INT UNSIGNED NOT NULL DEFAULT 0,
    UF_PUBLISHED_AT DATETIME DEFAULT NULL,
    UF_CREATED_AT DATETIME NOT NULL,
    UF_UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_rebit_leadhunter_external_lead_source_guid (UF_SOURCE, UF_GUID),
    KEY ix_rebit_leadhunter_external_lead_status (UF_STATUS, UF_ATTEMPTS),
    KEY ix_rebit_leadhunter_external_lead_created_at (UF_CREATED_AT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Application::getConnection()->queryExecute(
            'DROP TABLE IF EXISTS rebit_leadhunter_external_lead',
        );

        if (ModuleManager::isModuleInstalled('rebit.leadhunter')) {
            ModuleManager::unRegisterModule('rebit.leadhunter');
        }
    }
}
