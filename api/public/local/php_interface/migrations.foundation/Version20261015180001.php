<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261015180001 extends Version
{
    protected $author = 'codex';
    protected $description = 'J2: email key of a paid order, the moment the server confirmed a payment and the order email journal';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_order', 'mf_order_access_key', 'mf_file_download'] as $table) {
            if (!$connection->isTableExists($table)) {
                throw new \RuntimeException('Apply the E5 and J1 migrations first: missing ' . $table);
            }
        }
        // J2-DEC-01: a separate email key opens the same order page; the buyer key stays the only active buyer key.
        if (false === $connection->query("SHOW COLUMNS FROM mf_order_access_key LIKE 'KIND'")->fetch()) {
            $connection->queryExecute("ALTER TABLE mf_order_access_key ADD COLUMN KIND VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'buyer' AFTER ORDER_ID,
                ADD CONSTRAINT ck_mf_order_key_kind CHECK (KIND IN ('buyer', 'email'))");
            $connection->queryExecute('ALTER TABLE mf_order_access_key DROP INDEX ux_mf_order_key_active');
            $connection->queryExecute('ALTER TABLE mf_order_access_key DROP COLUMN ACTIVE_ORDER_ID');
            $connection->queryExecute("ALTER TABLE mf_order_access_key
                ADD COLUMN ACTIVE_ORDER_ID BIGINT UNSIGNED GENERATED ALWAYS AS (IF(REVOKED_AT IS NULL AND KIND = 'buyer', ORDER_ID, NULL)) STORED,
                ADD COLUMN ACTIVE_EMAIL_ORDER_ID BIGINT UNSIGNED GENERATED ALWAYS AS (IF(REVOKED_AT IS NULL AND KIND = 'email', ORDER_ID, NULL)) STORED,
                ADD UNIQUE KEY ux_mf_order_key_active (ACTIVE_ORDER_ID),
                ADD UNIQUE KEY ux_mf_order_key_email (ACTIVE_EMAIL_ORDER_ID)");
        }
        // J2-DEC-02: orders paid before this release have no confirmation moment and get no automatic letter.
        if (false === $connection->query("SHOW COLUMNS FROM mf_order LIKE 'PAID_RECORDED_AT'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_order ADD COLUMN PAID_RECORDED_AT DATETIME NULL AFTER PAID_AT,
                ADD KEY ix_mf_order_paid_recorded (PAID_RECORDED_AT, ID)');
        }
        // The dispatcher reads confirmed payments after this cursor; it starts at the release (J2-DEC-02).
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_file_delivery_cursor (
    ID TINYINT UNSIGNED NOT NULL,
    PAID_RECORDED_AT DATETIME NOT NULL,
    ORDER_ID BIGINT UNSIGNED NOT NULL DEFAULT 0,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    CONSTRAINT ck_mf_file_delivery_cursor_single CHECK (ID = 1)
) ENGINE=InnoDB
SQL);
        $connection->queryExecute('INSERT IGNORE INTO mf_file_delivery_cursor(ID,PAID_RECORDED_AT,ORDER_ID,UPDATED_AT) VALUES(1,UTC_TIMESTAMP(),0,UTC_TIMESTAMP())');
        // One automatic letter per order (AUTO_ORDER_ID); repeated letters of the buyer are bound to their Idempotency-Key.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_file_delivery (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ORDER_ID BIGINT UNSIGNED NOT NULL,
    KIND VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RECIPIENT VARCHAR(254) NOT NULL,
    OPERATION_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    IDEMPOTENCY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    AUTO_ORDER_ID BIGINT UNSIGNED GENERATED ALWAYS AS (IF(KIND = 'auto', ORDER_ID, NULL)) STORED,
    CREATED_AT DATETIME NOT NULL,
    QUEUED_AT DATETIME NULL,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_file_delivery_public (PUBLIC_ID),
    UNIQUE KEY ux_mf_file_delivery_auto (AUTO_ORDER_ID),
    UNIQUE KEY ux_mf_file_delivery_request (ORDER_ID, IDEMPOTENCY_HASH),
    KEY ix_mf_file_delivery_order (ORDER_ID, CREATED_AT, ID),
    KEY ix_mf_file_delivery_status (STATUS, ID),
    CONSTRAINT fk_mf_file_delivery_order FOREIGN KEY (ORDER_ID) REFERENCES mf_order(ID) ON DELETE RESTRICT,
    CONSTRAINT ck_mf_file_delivery_values CHECK (
        KIND IN ('auto', 'manual')
        AND STATUS IN ('held', 'queued', 'cancelled')
        AND (STATUS <> 'queued' OR (OPERATION_ID IS NOT NULL AND QUEUED_AT IS NOT NULL))
        AND (KIND = 'manual') = (IDEMPOTENCY_HASH IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        $connection->queryExecute('DROP TABLE IF EXISTS mf_file_delivery');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_file_delivery_cursor');
        if (false !== $connection->query("SHOW COLUMNS FROM mf_order LIKE 'PAID_RECORDED_AT'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_order DROP KEY ix_mf_order_paid_recorded, DROP COLUMN PAID_RECORDED_AT');
        }
        if (false !== $connection->query("SHOW COLUMNS FROM mf_order_access_key LIKE 'KIND'")->fetch()) {
            // Email keys only open order pages that the buyer key opens too: dropping them restores one key per order.
            $connection->queryExecute("DELETE FROM mf_order_access_key WHERE KIND = 'email'");
            $connection->queryExecute('ALTER TABLE mf_order_access_key DROP INDEX ux_mf_order_key_email, DROP INDEX ux_mf_order_key_active');
            $connection->queryExecute('ALTER TABLE mf_order_access_key DROP COLUMN ACTIVE_EMAIL_ORDER_ID, DROP COLUMN ACTIVE_ORDER_ID');
            $connection->queryExecute('ALTER TABLE mf_order_access_key DROP CHECK ck_mf_order_key_kind, DROP COLUMN KIND');
            $connection->queryExecute('ALTER TABLE mf_order_access_key ADD COLUMN ACTIVE_ORDER_ID BIGINT UNSIGNED GENERATED ALWAYS AS (IF(REVOKED_AT IS NULL, ORDER_ID, NULL)) STORED,
                ADD UNIQUE KEY ux_mf_order_key_active (ACTIVE_ORDER_ID)');
        }
    }
}
