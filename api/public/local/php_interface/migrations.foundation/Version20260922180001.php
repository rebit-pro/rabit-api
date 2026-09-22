<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260922180001 extends Version
{
    protected $author = 'codex';
    protected $description = 'D3: results of staff request transfers';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_group', 'mf_staff_request_row'] as $parent) {
            if (!$connection->isTableExists($parent)) {
                throw new \RuntimeException('D3 requires the merged C3 and F1 schema.');
            }
        }
        if (false !== $connection->query("SHOW COLUMNS FROM mf_staff_request_row LIKE 'TRANSFER_GROUP_ID'")->fetch()) {
            return;
        }
        $groupType = $this->integerType('b_hlbd_mf_group', 'ID');
        // One atomic ALTER: the four result columns appear together and are filled together by HND-11.
        $connection->queryExecute("ALTER TABLE mf_staff_request_row
            ADD TRANSFER_FROM_CODE VARCHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL,
            ADD TRANSFER_GROUP_ID {$groupType} NULL,
            ADD TRANSFER_CODE VARCHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL,
            ADD TRANSFER_PHOTO_IDS_JSON TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
            ADD CONSTRAINT ck_mf_staff_request_row_transfer CHECK (
                (TRANSFER_FROM_CODE IS NULL AND TRANSFER_GROUP_ID IS NULL AND TRANSFER_CODE IS NULL AND TRANSFER_PHOTO_IDS_JSON IS NULL)
                OR (TRANSFER_FROM_CODE REGEXP '^[A-Z]{1,3}$' AND TRANSFER_GROUP_ID IS NOT NULL
                    AND TRANSFER_CODE REGEXP '^[A-Z]{1,3}$' AND TRANSFER_PHOTO_IDS_JSON IS NOT NULL)),
            ADD CONSTRAINT fk_mf_staff_request_row_transfer_group FOREIGN KEY (TRANSFER_GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT");
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_staff_request_row')
            || false === $connection->query("SHOW COLUMNS FROM mf_staff_request_row LIKE 'TRANSFER_GROUP_ID'")->fetch()) {
            return;
        }
        if (false !== $connection->query('SELECT 1 FROM mf_staff_request_row WHERE TRANSFER_GROUP_ID IS NOT NULL LIMIT 1')->fetch()) {
            throw new \RuntimeException('D3 transfer results exist; destructive rollback is forbidden.');
        }
        $connection->queryExecute('ALTER TABLE mf_staff_request_row
            DROP FOREIGN KEY fk_mf_staff_request_row_transfer_group,
            DROP CHECK ck_mf_staff_request_row_transfer');
        $connection->queryExecute('ALTER TABLE mf_staff_request_row
            DROP INDEX fk_mf_staff_request_row_transfer_group,
            DROP COLUMN TRANSFER_PHOTO_IDS_JSON,
            DROP COLUMN TRANSFER_CODE,
            DROP COLUMN TRANSFER_GROUP_ID,
            DROP COLUMN TRANSFER_FROM_CODE');
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
