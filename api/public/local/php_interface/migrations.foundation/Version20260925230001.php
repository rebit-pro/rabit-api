<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

final class Version20260925230001 extends Version
{
    protected $author = 'codex';
    protected $description = 'OPS legal: journal of accepted legal documents (orders and staff), morefoto.legal module';

    public function up(): void
    {
        // Evidence of consent is the subject, the exact document version and the moment; browser network data is not kept.
        Application::getConnection()->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_legal_consent (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    CONTEXT VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    SUBJECT_ID BIGINT UNSIGNED NOT NULL,
    DOCUMENT_CODE VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    DOCUMENT_VERSION VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ACCEPTED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_legal_consent (CONTEXT, SUBJECT_ID, DOCUMENT_CODE, DOCUMENT_VERSION)
) ENGINE=InnoDB
SQL);
        if (!ModuleManager::isModuleInstalled('morefoto.legal')) {
            ModuleManager::registerModule('morefoto.legal');
        }
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists('mf_legal_consent') && false !== $connection->query('SELECT 1 FROM mf_legal_consent LIMIT 1')->fetch()) {
            throw new \RuntimeException('Consent evidence exists; destructive rollback is forbidden.');
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_legal_consent');
        if (ModuleManager::isModuleInstalled('morefoto.legal')) {
            ModuleManager::unRegisterModule('morefoto.legal');
        }
    }
}
