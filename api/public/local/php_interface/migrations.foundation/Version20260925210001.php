<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260925210001 extends Version
{
    private const string QUESTION_OWNER = "(AUTHOR='parent' AND CHAR_LENGTH(KEY_HASH)=64 AND GROUP_ID IS NOT NULL AND STAFF_USER_ID IS NULL)"
        . " OR (AUTHOR='staff' AND STAFF_USER_ID>0 AND KEY_HASH IS NULL AND GROUP_ID IS NULL)";
    private const string GUEST_OWNER = " OR (AUTHOR='guest' AND KEY_HASH IS NULL AND GROUP_ID IS NULL AND STAFF_USER_ID IS NULL)";

    protected $author = 'codex';
    protected $description = 'OPS-login-feedback: guest feedback from the login page goes through the K3 question outbox';

    public function up(): void
    {
        $this->constraints(self::QUESTION_OWNER . self::GUEST_OWNER, "'parent','staff','guest'");
    }

    public function down(): void
    {
        if (false !== Application::getConnection()->query("SELECT 1 FROM mf_support_question WHERE AUTHOR='guest' LIMIT 1")->fetch()) {
            throw new \RuntimeException('Guest feedback exists; destructive rollback is forbidden.');
        }
        $this->constraints(self::QUESTION_OWNER, "'parent','staff'");
    }

    private function constraints(string $owner, string $authors): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_support_question')) {
            throw new \RuntimeException('OPS-login-feedback requires the merged K3 schema.');
        }
        // A guest has neither a gallery group nor an account: the curator answers by the contact in the context.
        $connection->queryExecute('ALTER TABLE mf_support_question DROP CONSTRAINT ck_mf_support_question_owner,'
            . ' ADD CONSTRAINT ck_mf_support_question_owner CHECK (' . $owner . ')');
        $connection->queryExecute('ALTER TABLE mf_support_message DROP CONSTRAINT ck_mf_support_message_author,'
            . " ADD CONSTRAINT ck_mf_support_message_author CHECK ((AUTHOR='curator' AND DELIVERY_STATUS IS NULL AND MAX_MID IS NOT NULL)"
            . ' OR (AUTHOR IN (' . $authors . ") AND DELIVERY_STATUS IN ('pending','processing','delivered','failed','unknown')))");
    }
}
