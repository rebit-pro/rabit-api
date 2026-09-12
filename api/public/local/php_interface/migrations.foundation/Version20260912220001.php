<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260912220001 extends Version
{
    protected $author = 'codex';
    protected $description = 'E2: durable catalogue mutation idempotency; no product seeds';

    public function up(): void
    {
        Application::getConnection()->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_catalog_idempotency (
    ACTOR_ID INT UNSIGNED NOT NULL,
    HTTP_METHOD VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RESOURCE_KEY VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    IDEMPOTENCY_KEY CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PAYLOAD_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRODUCT_UUID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RESULT_REVISION BIGINT NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ACTOR_ID, HTTP_METHOD, RESOURCE_KEY, IDEMPOTENCY_KEY),
    CONSTRAINT ck_mf_catalog_idempotency CHECK (ACTOR_ID > 0 AND RESULT_REVISION > 0 AND HTTP_METHOD IN ('POST', 'PATCH'))
) ENGINE=InnoDB
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_catalog_idempotency')) {
            return;
        }
        if (false !== $connection->query('SELECT ACTOR_ID FROM mf_catalog_idempotency LIMIT 1')->fetch()) {
            throw new \RuntimeException('Idempotency records exist; preserve mutation history when rolling back application code.');
        }
        $connection->queryExecute('DROP TABLE mf_catalog_idempotency');
    }
}
