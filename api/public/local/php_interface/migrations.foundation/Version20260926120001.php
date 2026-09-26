<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260926120001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Issue #111: hourly guest feedback counter per keyed hash of the client address';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_support_question')) {
            throw new \RuntimeException('Issue #111 requires the merged K3 schema.');
        }
        // Only an HMAC of the address is stored, unlinked from the question; windows older than an hour are deleted.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_support_guest_address (
    ADDRESS_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    WINDOW_STARTED_AT DATETIME NOT NULL,
    QUESTIONS SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (ADDRESS_HASH),
    KEY ix_mf_support_guest_address_window (WINDOW_STARTED_AT),
    CONSTRAINT ck_mf_support_guest_address_hash CHECK (CHAR_LENGTH(ADDRESS_HASH)=64)
) ENGINE=InnoDB
SQL);
    }

    public function down(): void
    {
        // Counters live for an hour and carry no business data: dropping them only resets the limits.
        Application::getConnection()->queryExecute('DROP TABLE IF EXISTS mf_support_guest_address');
    }
}
