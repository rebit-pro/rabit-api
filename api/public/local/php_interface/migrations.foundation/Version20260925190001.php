<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260925190001 extends Version
{
    private const array TABLES = ['mf_payment_notification', 'mf_payment_fact', 'mf_payment_attempt'];

    protected $author = 'codex';
    protected $description = 'G1: payment attempts, confirmed payment facts, provider notification inbox; paid moment of an order';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (false === $connection->query("SHOW COLUMNS FROM mf_order LIKE 'PAID_AT'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_order ADD PAID_AT DATETIME NULL AFTER PRODUCTION_STATUS,'
                . ' ADD LATE_PAYMENT TINYINT(1) NOT NULL DEFAULT 0 AFTER PAID_AT,'
                . ' ADD KEY ix_mf_order_late (LATE_PAYMENT, CREATED_AT, ID),'
                . " ADD CONSTRAINT ck_mf_order_paid CHECK ((PAYMENT_STATUS = 'paid') = (PAID_AT IS NOT NULL) AND (LATE_PAYMENT = 0 OR PAID_AT IS NOT NULL))");
        }
        // ACTIVE_ORDER_ID equals ORDER_ID only while the outcome is open: one unknown/pending attempt per order.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_payment_attempt (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ORDER_ID BIGINT UNSIGNED NOT NULL,
    ORDER_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ORDER_NUMBER VARCHAR(20) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    ORDER_VERSION VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    INSTITUTION_ID BIGINT UNSIGNED NOT NULL,
    INSTITUTION_NAME VARCHAR(255) NOT NULL,
    GROUP_NAME VARCHAR(255) NOT NULL,
    AMOUNT BIGINT UNSIGNED NOT NULL,
    CURRENCY CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PAYMENT_METHOD VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    SHOP_ID VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ACTIVE_ORDER_ID BIGINT UNSIGNED NULL,
    PRECEDING_ID BIGINT UNSIGNED NULL,
    CLIENT_KEY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    REQUEST_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER_KEY CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER_PAYMENT_ID VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    CONFIRMATION_URL VARCHAR(1024) CHARACTER SET ascii COLLATE ascii_bin NULL,
    CANCEL_REASON VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PAID_AT DATETIME NULL,
    INCOME_AMOUNT BIGINT UNSIGNED NULL,
    LATE_PAYMENT TINYINT(1) NOT NULL DEFAULT 0,
    CHECK_COUNT INT UNSIGNED NOT NULL DEFAULT 0,
    NEXT_CHECK_AT DATETIME NULL,
    LAST_CHECK_AT DATETIME NULL,
    CREATED_AT DATETIME NOT NULL,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_payment_attempt_public (PUBLIC_ID),
    UNIQUE KEY ux_mf_payment_attempt_active (ACTIVE_ORDER_ID),
    UNIQUE KEY ux_mf_payment_attempt_client (ORDER_ID, CLIENT_KEY_HASH),
    UNIQUE KEY ux_mf_payment_attempt_provider_key (PROVIDER_KEY),
    UNIQUE KEY ux_mf_payment_attempt_provider (PROVIDER, PROVIDER_PAYMENT_ID),
    KEY ix_mf_payment_attempt_order (ORDER_ID, ID),
    KEY ix_mf_payment_attempt_check (NEXT_CHECK_AT),
    KEY ix_mf_payment_attempt_created (CREATED_AT, ID),
    KEY ix_mf_payment_attempt_institution (INSTITUTION_ID, CREATED_AT, ID),
    KEY ix_mf_payment_attempt_number (ORDER_NUMBER),
    CONSTRAINT fk_mf_payment_attempt_order FOREIGN KEY (ORDER_ID) REFERENCES mf_order(ID) ON DELETE RESTRICT,
    CONSTRAINT ck_mf_payment_attempt_values CHECK (
        STATUS IN ('unknown', 'pending', 'succeeded', 'canceled')
        AND (STATUS IN ('unknown', 'pending')) = (ACTIVE_ORDER_ID IS NOT NULL)
        AND (ACTIVE_ORDER_ID IS NULL OR ACTIVE_ORDER_ID = ORDER_ID)
        AND (STATUS = 'succeeded') = (PAID_AT IS NOT NULL)
        AND CURRENCY = 'RUB' AND AMOUNT > 0
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_payment_fact (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ATTEMPT_ID BIGINT UNSIGNED NOT NULL,
    ORDER_ID BIGINT UNSIGNED NOT NULL,
    PROVIDER VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER_PAYMENT_ID VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    AMOUNT BIGINT UNSIGNED NOT NULL,
    INCOME_AMOUNT BIGINT UNSIGNED NULL,
    PAID_AT DATETIME NOT NULL,
    LATE_PAYMENT TINYINT(1) NOT NULL,
    CONFIRMED_BY VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_payment_fact_attempt (ATTEMPT_ID),
    UNIQUE KEY ux_mf_payment_fact_provider (PROVIDER, PROVIDER_PAYMENT_ID),
    KEY ix_mf_payment_fact_order (ORDER_ID),
    CONSTRAINT fk_mf_payment_fact_attempt FOREIGN KEY (ATTEMPT_ID) REFERENCES mf_payment_attempt(ID) ON DELETE RESTRICT,
    CONSTRAINT ck_mf_payment_fact_values CHECK (AMOUNT > 0 AND CONFIRMED_BY IN ('start', 'return', 'notification', 'reconcile'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        // Provider notifications carry no event ID: EVENT_KEY is `event:object id`, the body itself is not stored.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_payment_notification (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PROVIDER VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    EVENT_KEY VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    EVENT VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER_OBJECT_ID VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RECEIVED_AT DATETIME NOT NULL,
    PROCESSED_AT DATETIME NULL,
    RESULT VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_payment_notification_event (PROVIDER, EVENT_KEY)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        // Money facts never disappear silently: rollback only removes tables that stayed empty.
        foreach (self::TABLES as $table) {
            if ($connection->isTableExists($table) && false !== $connection->query('SELECT ID FROM ' . $table . ' LIMIT 1')->fetch()) {
                throw new \RuntimeException('Payment data exists, refusing to drop ' . $table . '.');
            }
        }
        foreach (self::TABLES as $table) {
            $connection->queryExecute('DROP TABLE IF EXISTS ' . $table);
        }
        if (false !== $connection->query("SHOW COLUMNS FROM mf_order LIKE 'PAID_AT'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_order DROP CHECK ck_mf_order_paid, DROP KEY ix_mf_order_late, DROP COLUMN LATE_PAYMENT, DROP COLUMN PAID_AT');
        }
    }
}
