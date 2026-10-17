<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261017100001 extends Version
{
    protected $author = 'codex';
    protected $description = 'J5: attachments of email operations by reference and the letters of an order email with their originals';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_rebit_notification_operation', 'mf_file_delivery'] as $table) {
            if (!$connection->isTableExists($table)) {
                throw new \RuntimeException('Apply the H1 and J2 migrations first: missing ' . $table);
            }
        }
        // J5 T-03: an operation keeps references to the files only; the transport reads the bytes when it sends the letter.
        if (false === $connection->query("SHOW COLUMNS FROM b_rebit_notification_operation LIKE 'ATTACHMENTS'")->fetch()) {
            $connection->queryExecute('ALTER TABLE b_rebit_notification_operation ADD COLUMN ATTACHMENTS JSON NULL AFTER BODY_HTML');
        }
        // LETTERS IS NULL — the letters of the delivery are not planned yet; the plan and its letters are written together.
        if (false === $connection->query("SHOW COLUMNS FROM mf_file_delivery LIKE 'LETTERS'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_file_delivery ADD COLUMN LETTERS SMALLINT UNSIGNED NULL AFTER OPERATION_ID,
                ADD COLUMN LINK_ONLY JSON NULL AFTER LETTERS');
        }
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_file_delivery_part (
    DELIVERY_ID BIGINT UNSIGNED NOT NULL,
    NUMBER SMALLINT UNSIGNED NOT NULL,
    FILES JSON NOT NULL,
    BYTES BIGINT UNSIGNED NOT NULL,
    OPERATION_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    CREATED_AT DATETIME NOT NULL,
    QUEUED_AT DATETIME NULL,
    PRIMARY KEY (DELIVERY_ID, NUMBER),
    CONSTRAINT fk_mf_file_delivery_part FOREIGN KEY (DELIVERY_ID) REFERENCES mf_file_delivery(ID) ON DELETE CASCADE,
    CONSTRAINT ck_mf_file_delivery_part CHECK (NUMBER >= 1 AND (OPERATION_ID IS NULL) = (QUEUED_AT IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        // J5 T-09: a letter J2 already queued becomes letter 1 of 1 without attachments.
        $connection->queryExecute("INSERT IGNORE INTO mf_file_delivery_part(DELIVERY_ID,NUMBER,FILES,BYTES,OPERATION_ID,CREATED_AT,QUEUED_AT)
            SELECT ID,1,JSON_ARRAY(),0,OPERATION_ID,QUEUED_AT,QUEUED_AT FROM mf_file_delivery WHERE STATUS='queued' AND LETTERS IS NULL");
        $connection->queryExecute("UPDATE mf_file_delivery SET LETTERS=1,LINK_ONLY=JSON_ARRAY() WHERE STATUS='queued' AND LETTERS IS NULL");
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        // J2 keeps OPERATION_ID of the first letter, so its view still shows the delivery after the rollback.
        $connection->queryExecute('DROP TABLE IF EXISTS mf_file_delivery_part');
        if (false !== $connection->query("SHOW COLUMNS FROM mf_file_delivery LIKE 'LETTERS'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_file_delivery DROP COLUMN LINK_ONLY, DROP COLUMN LETTERS');
        }
        if (false !== $connection->query("SHOW COLUMNS FROM b_rebit_notification_operation LIKE 'ATTACHMENTS'")->fetch()) {
            $connection->queryExecute('ALTER TABLE b_rebit_notification_operation DROP COLUMN ATTACHMENTS');
        }
    }
}
