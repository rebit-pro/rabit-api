<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

final class Version20260925150001 extends Version
{
    protected $author = 'codex';
    protected $description = 'K3: questions to the curator through the MAX group, delivery state and idempotency';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('b_hlbd_mf_group')) {
            throw new \RuntimeException('K3 requires the merged C3 schema.');
        }
        $groupType = $this->integerType('b_hlbd_mf_group', 'ID');
        // A parent question belongs to the gallery group and is reached by its key; a staff question — by the user.
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_support_question (
            ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
            AUTHOR VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            KEY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            GROUP_ID {$groupType} NULL,
            STAFF_USER_ID INT UNSIGNED NULL,
            AUTHOR_NAME VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            CONTEXT VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            CREATED_AT DATETIME NOT NULL,
            LAST_MESSAGE_AT DATETIME NOT NULL,
            PRIMARY KEY (ID),
            UNIQUE KEY ux_mf_support_question_key (KEY_HASH),
            UNIQUE KEY ux_mf_support_question_staff (STAFF_USER_ID),
            KEY ix_mf_support_question_group (GROUP_ID, CREATED_AT),
            CONSTRAINT ck_mf_support_question_owner CHECK (
                (AUTHOR='parent' AND CHAR_LENGTH(KEY_HASH)=64 AND GROUP_ID IS NOT NULL AND STAFF_USER_ID IS NULL)
                OR (AUTHOR='staff' AND STAFF_USER_ID>0 AND KEY_HASH IS NULL AND GROUP_ID IS NULL)),
            CONSTRAINT fk_mf_support_question_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        // Outgoing replies are their own outbox; a curator reply is stored once per incoming MAX mid.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_support_message (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    QUESTION_ID INT UNSIGNED NOT NULL,
    AUTHOR VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    AUTHOR_NAME VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    BODY TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    DELIVERY_STATUS VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ATTEMPTS TINYINT UNSIGNED NOT NULL DEFAULT 0,
    NEXT_ATTEMPT_AT DATETIME NULL,
    PROCESSING_STARTED_AT DATETIME NULL,
    LAST_ERROR_CODE VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    MAX_MID VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_support_message_mid (MAX_MID),
    KEY ix_mf_support_message_question (QUESTION_ID, ID),
    KEY ix_mf_support_message_due (DELIVERY_STATUS, NEXT_ATTEMPT_AT, ID),
    CONSTRAINT ck_mf_support_message_author CHECK (
        (AUTHOR='curator' AND DELIVERY_STATUS IS NULL AND MAX_MID IS NOT NULL)
        OR (AUTHOR IN ('parent','staff') AND DELIVERY_STATUS IN ('pending','processing','delivered','failed','unknown'))),
    CONSTRAINT ck_mf_support_message_attempts CHECK (ATTEMPTS<=10),
    CONSTRAINT fk_mf_support_message_question FOREIGN KEY (QUESTION_ID) REFERENCES mf_support_question(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB
SQL);
        // Only the SHA-256 of the client's key is stored; the parent's question key is sealed with the key itself.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_support_idempotency (
    SCOPE_KEY VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    KEY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PAYLOAD_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    QUESTION_ID INT UNSIGNED NOT NULL,
    SEALED_KEY VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (SCOPE_KEY, KEY_HASH),
    KEY ix_mf_support_idempotency_created (CREATED_AT),
    CONSTRAINT fk_mf_support_idempotency_question FOREIGN KEY (QUESTION_ID) REFERENCES mf_support_question(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB
SQL);
        // GET /chats is deprecated in MAX: the group ID is learnt from incoming events and chosen by an operator.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_support_max_chat (
    CHAT_ID BIGINT NOT NULL,
    LAST_EVENT VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    BOT_PRESENT TINYINT(1) NOT NULL,
    SEEN_AT DATETIME NOT NULL,
    PRIMARY KEY (CHAT_ID)
) ENGINE=InnoDB
SQL);
        if (!ModuleManager::isModuleInstalled('morefoto.support')) {
            ModuleManager::registerModule('morefoto.support');
        }
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_support_question', 'mf_support_message'] as $table) {
            if ($connection->isTableExists($table) && false !== $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetch()) {
                throw new \RuntimeException('K3 question data exists; destructive rollback is forbidden.');
            }
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_support_max_chat');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_support_idempotency');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_support_message');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_support_question');
        if (ModuleManager::isModuleInstalled('morefoto.support')) {
            ModuleManager::unRegisterModule('morefoto.support');
        }
    }

    private function integerType(string $table, string $column): string
    {
        $row = Application::getConnection()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$column}'")->fetch();
        $type = false === $row ? '' : strtolower((string)$row['COLUMN_TYPE']);
        if (1 !== preg_match('/^(?:big)?int(?:\([0-9]+\))?(?: unsigned)?$/D', $type)) {
            throw new \RuntimeException('Unsupported parent integer type for ' . $table . '.' . $column);
        }

        return $type;
    }
}
