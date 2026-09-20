<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Sprint\Migration\Exceptions\HelperException;

final class Version20260920110001 extends Version
{
    private const string EVENT_NAME = 'REBIT_NOTIFICATION_OUTGOING_EMAIL';
    private const string EVENT_SUBJECT = '#SUBJECT#';
    private const string SITE_ID = 's1';

    protected $author = 'codex';
    protected $description = 'H1: durable outgoing email operations, attempts and Bitrix mail event';

    /**
     * @throws HelperException
     */
    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('b_rebit_notification_operation')) {
            $connection->queryExecute(<<<'SQL'
CREATE TABLE b_rebit_notification_operation (
    ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CONSUMER_KEY VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    DEDUP_KEY VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PAYLOAD_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CHANNEL VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RECIPIENT VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    SUBJECT VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    BODY MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ATTEMPTS INT UNSIGNED NOT NULL DEFAULT 0,
    MAX_ATTEMPTS INT UNSIGNED NOT NULL,
    NEXT_ATTEMPT_AT DATETIME NULL,
    PROCESSING_STARTED_AT DATETIME NULL,
    ACCEPTED_AT DATETIME NULL,
    LAST_ERROR_CODE VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    CREATED_AT DATETIME NOT NULL,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_rebit_notification_dedup (CONSUMER_KEY, DEDUP_KEY),
    KEY ix_rebit_notification_due (STATUS, NEXT_ATTEMPT_AT, CREATED_AT),
    CONSTRAINT ck_rebit_notification_channel CHECK (CHANNEL='email'),
    CONSTRAINT ck_rebit_notification_status CHECK (STATUS IN ('pending','processing','retryWait','accepted','unknown','failed')),
    CONSTRAINT ck_rebit_notification_attempts CHECK (MAX_ATTEMPTS BETWEEN 1 AND 10 AND ATTEMPTS<=MAX_ATTEMPTS)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }
        if (!$connection->isTableExists('b_rebit_notification_attempt')) {
            $connection->queryExecute(<<<'SQL'
CREATE TABLE b_rebit_notification_attempt (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    OPERATION_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ATTEMPT_NO INT UNSIGNED NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ERROR_CODE VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    STARTED_AT DATETIME NOT NULL,
    FINISHED_AT DATETIME NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_rebit_notification_attempt (OPERATION_ID, ATTEMPT_NO),
    KEY ix_rebit_notification_attempt_status (STATUS, STARTED_AT),
    CONSTRAINT fk_rebit_notification_attempt_operation FOREIGN KEY (OPERATION_ID)
        REFERENCES b_rebit_notification_operation(ID) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_rebit_notification_attempt_status CHECK (STATUS IN ('started','accepted','rejected','unknown'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }
        $event = $this->getHelperManager()->Event();
        $event->saveEventType(self::EVENT_NAME, [
            'LID' => 'ru',
            'NAME' => 'Исходящее письмо Notification',
            'DESCRIPTION' => "#EMAIL_TO# - E-mail получателя\n#SUBJECT# - Тема\n#BODY# - HTML-безопасное тело",
        ]);
        $event->saveEventMessage(self::EVENT_NAME, [
            'ACTIVE' => 'Y',
            'LID' => self::SITE_ID,
            'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
            'EMAIL_TO' => '#EMAIL_TO#',
            'SUBJECT' => self::EVENT_SUBJECT,
            'BODY_TYPE' => 'html',
            'MESSAGE' => '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#17233b">#BODY#</div>',
        ]);
    }

    /**
     * @throws HelperException
     */
    public function down(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists('b_rebit_notification_operation')
            && false !== $connection->query('SELECT ID FROM b_rebit_notification_operation LIMIT 1')->fetch()) {
            throw new \RuntimeException('H1 notification operations exist; destructive rollback is forbidden.');
        }
        if ($connection->isTableExists('b_rebit_notification_attempt')) {
            $connection->dropTable('b_rebit_notification_attempt');
        }
        if ($connection->isTableExists('b_rebit_notification_operation')) {
            $connection->dropTable('b_rebit_notification_operation');
        }
        $event = $this->getHelperManager()->Event();
        $event->deleteEventMessage([
            'EVENT_NAME' => self::EVENT_NAME,
            'SUBJECT' => self::EVENT_SUBJECT,
        ]);
        $event->deleteEventType([
            'EVENT_NAME' => self::EVENT_NAME,
            'LID' => 'ru',
        ]);
    }
}
