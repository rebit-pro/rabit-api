<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261012100001 extends Version
{
    /** Clauses of the E5 CHECK that E8 keeps as they are. */
    private const string ORDER_VALUES = "AUDIENCE IN ('regular', 'staff')"
        . " AND PAYMENT_STATUS IN ('unpaid', 'pending', 'declined', 'paid')"
        . " AND PRODUCTION_STATUS IN ('not-started', 'queued', 'printing', 'ready', 'delivered')"
        . " AND (RECEIPT_CHANNEL IS NULL OR RECEIPT_CHANNEL IN ('email', 'max'))"
        . ' AND TOTAL + DISCOUNT + GIFT_SAVING = SUBTOTAL AND ITEM_COUNT > 0 AND VERSION > 0'
        . " AND JSON_TYPE(GIFTS) = 'ARRAY'";
    /** E8: email is the only buyer contact; a stored name or phone of an earlier order keeps the E5 rule. */
    private const string BUYER_E8 = "(BUYER_NAME IS NULL OR CHAR_LENGTH(TRIM(BUYER_NAME)) >= 2) AND BUYER_EMAIL <> ''"
        . " AND (BUYER_PHONE IS NULL OR BUYER_PHONE REGEXP '^[+][0-9]{10,15}$')";
    private const string BUYER_E5 = "CHAR_LENGTH(TRIM(BUYER_NAME)) >= 2 AND BUYER_EMAIL <> '' AND BUYER_PHONE REGEXP '^[+][0-9]{10,15}$'";

    protected $author = 'claude';
    protected $description = 'E8: an order needs only the buyer email; name and phone become optional, earlier values stay';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_order')) {
            throw new \RuntimeException('E8 requires the merged E5 orders.');
        }
        // One ALTER: the columns and the CHECK change together, a failure leaves the E5 schema as it was.
        $connection->queryExecute('ALTER TABLE mf_order MODIFY BUYER_NAME VARCHAR(100) NULL,'
            . ' MODIFY BUYER_PHONE VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,'
            . ' DROP CHECK ck_mf_order_values,'
            . ' ADD CONSTRAINT ck_mf_order_values CHECK (' . self::ORDER_VALUES . ' AND ' . self::BUYER_E8 . ')');
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (false !== $connection->query('SELECT 1 FROM mf_order WHERE BUYER_NAME IS NULL OR BUYER_PHONE IS NULL LIMIT 1')->fetch()) {
            throw new \RuntimeException('Orders without the buyer name or phone exist; E5 columns would need made-up values.');
        }
        $connection->queryExecute('ALTER TABLE mf_order MODIFY BUYER_NAME VARCHAR(100) NOT NULL,'
            . ' MODIFY BUYER_PHONE VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . ' DROP CHECK ck_mf_order_values,'
            . ' ADD CONSTRAINT ck_mf_order_values CHECK (' . self::ORDER_VALUES . ' AND ' . self::BUYER_E5 . ')');
    }
}
