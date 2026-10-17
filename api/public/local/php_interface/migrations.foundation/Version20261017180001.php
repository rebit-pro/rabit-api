<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261017180001 extends Version
{
    protected $author = 'codex';
    protected $description = 'G2: receipt snapshot of a payment attempt, payment and closing receipts with their fiscal status';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_payment_attempt')) {
            throw new \RuntimeException('Apply the G1 migration first: missing mf_payment_attempt');
        }
        // The receipt goes with the payment request, so it is fixed when the attempt starts and repeated byte for byte.
        if (false === $connection->query("SHOW COLUMNS FROM mf_payment_attempt LIKE 'RECEIPT'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_payment_attempt ADD RECEIPT JSON NULL AFTER PROVIDER_KEY');
        }
        // One payment receipt per paid attempt and one closing receipt for its print; HANDED_AT marks the closing one.
        // SENT_AT is the first creation request of a closing receipt: its idempotence key is only reusable for a day after it.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_payment_receipt (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    KIND VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ATTEMPT_ID BIGINT UNSIGNED NOT NULL,
    ORDER_ID BIGINT UNSIGNED NOT NULL,
    ORDER_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER_PAYMENT_ID VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER_KEY CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PROVIDER_RECEIPT_ID VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    AMOUNT BIGINT UNSIGNED NOT NULL,
    ITEMS JSON NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ERROR VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    FISCAL_DOCUMENT_NUMBER VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    FISCAL_STORAGE_NUMBER VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    FISCAL_ATTRIBUTE VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    REGISTERED_AT DATETIME NULL,
    HANDED_AT DATETIME NULL,
    HANDED_BY BIGINT UNSIGNED NULL,
    SENT_AT DATETIME NULL,
    CHECK_COUNT INT UNSIGNED NOT NULL DEFAULT 0,
    NEXT_CHECK_AT DATETIME NULL,
    LAST_CHECK_AT DATETIME NULL,
    CREATED_AT DATETIME NOT NULL,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_payment_receipt_public (PUBLIC_ID),
    UNIQUE KEY ux_mf_payment_receipt_kind (ATTEMPT_ID, KIND),
    KEY ix_mf_payment_receipt_order (ORDER_ID),
    KEY ix_mf_payment_receipt_order_public (ORDER_PUBLIC_ID),
    KEY ix_mf_payment_receipt_due (STATUS, NEXT_CHECK_AT, ID),
    CONSTRAINT fk_mf_payment_receipt_attempt FOREIGN KEY (ATTEMPT_ID) REFERENCES mf_payment_attempt(ID) ON DELETE RESTRICT,
    CONSTRAINT ck_mf_payment_receipt_values CHECK (
        KIND IN ('payment', 'closing')
        AND STATUS IN ('queued', 'pending', 'succeeded', 'canceled', 'failed')
        AND AMOUNT > 0
        AND (KIND = 'closing') = (HANDED_AT IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        // Fiscal documents never disappear silently: rollback only removes a table that stayed empty.
        if ($connection->isTableExists('mf_payment_receipt') && false !== $connection->query('SELECT ID FROM mf_payment_receipt LIMIT 1')->fetch()) {
            throw new \RuntimeException('Receipt data exists, refusing to drop mf_payment_receipt.');
        }
        $snapshot = false !== $connection->query("SHOW COLUMNS FROM mf_payment_attempt LIKE 'RECEIPT'")->fetch();
        if ($snapshot && false !== $connection->query('SELECT ID FROM mf_payment_attempt WHERE RECEIPT IS NOT NULL LIMIT 1')->fetch()) {
            throw new \RuntimeException('Receipt snapshots exist, refusing to drop mf_payment_attempt.RECEIPT.');
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_payment_receipt');
        if ($snapshot) {
            $connection->queryExecute('ALTER TABLE mf_payment_attempt DROP COLUMN RECEIPT');
        }
    }
}
