<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260919130001 extends Version
{
    protected $author = 'codex';
    protected $description = 'D1: private originals, deduplication and protected preview processing';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_shoot', 'b_hlbd_mf_group'] as $parent) {
            if (!$connection->isTableExists($parent)) {
                throw new \RuntimeException('D1 requires the merged C3 structure schema.');
            }
        }
        $helper = $this->getHelperManager()->Hlblock();
        $id = (int)$helper->saveHlblock(['NAME' => 'MfPhoto', 'TABLE_NAME' => 'b_hlbd_mf_photo']);
        foreach ([
            'UF_PUBLIC_ID' => ['string', true],
            'UF_SHOOT_ID' => ['integer', true],
            'UF_GROUP_ID' => ['integer', true],
            'UF_ORIGINAL_GROUP_ID' => ['integer', true],
            'UF_FILENAME' => ['string', true],
            'UF_MIME_TYPE' => ['string', true],
            'UF_BYTES' => ['integer', true],
            'UF_WIDTH' => ['integer', true],
            'UF_HEIGHT' => ['integer', true],
            'UF_FINGERPRINT' => ['string', true],
            'UF_DEDUP_KEY' => ['string', false],
            'UF_STATUS' => ['string', true],
            'UF_ORIGINAL_PATH' => ['string', false],
            'UF_THUMB_SRC' => ['string', false],
            'UF_PREVIEW_SRC' => ['string', false],
            'UF_ERROR_CODE' => ['string', false],
            'UF_EXISTING_PHOTO_ID' => ['integer', false],
            'UF_JOB_STATE' => ['string', true],
            'UF_ATTEMPTS' => ['integer', true],
            'UF_REVISION' => ['integer', true],
            'UF_CREATED_AT' => ['datetime', true],
            'UF_UPDATED_AT' => ['datetime', true],
        ] as $name => [$type, $required]) {
            $this->field($id, $name, $type, $required);
        }
        $shootType = $this->columnType('b_hlbd_mf_shoot', 'ID');
        $groupType = $this->columnType('b_hlbd_mf_group', 'ID');
        $photoType = $this->columnType('b_hlbd_mf_photo', 'ID');
        $connection->queryExecute("ALTER TABLE b_hlbd_mf_photo ENGINE=InnoDB,
            MODIFY UF_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            MODIFY UF_SHOOT_ID {$shootType} NOT NULL,
            MODIFY UF_GROUP_ID {$groupType} NOT NULL,
            MODIFY UF_ORIGINAL_GROUP_ID {$groupType} NOT NULL,
            MODIFY UF_FILENAME VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            MODIFY UF_MIME_TYPE VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            MODIFY UF_BYTES BIGINT UNSIGNED NOT NULL,
            MODIFY UF_WIDTH INT UNSIGNED NOT NULL,
            MODIFY UF_HEIGHT INT UNSIGNED NOT NULL,
            MODIFY UF_FINGERPRINT CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            MODIFY UF_DEDUP_KEY VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
            MODIFY UF_STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            MODIFY UF_ORIGINAL_PATH VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            MODIFY UF_THUMB_SRC VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            MODIFY UF_PREVIEW_SRC VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            MODIFY UF_ERROR_CODE VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            MODIFY UF_EXISTING_PHOTO_ID {$photoType} NULL,
            MODIFY UF_JOB_STATE VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            MODIFY UF_ATTEMPTS INT UNSIGNED NOT NULL,
            MODIFY UF_REVISION INT UNSIGNED NOT NULL,
            MODIFY UF_CREATED_AT DATETIME NOT NULL,
            MODIFY UF_UPDATED_AT DATETIME NOT NULL");
        $this->index('ux_mf_photo_public', 'UNIQUE KEY ux_mf_photo_public (UF_PUBLIC_ID)');
        $this->index('ux_mf_photo_dedup', 'UNIQUE KEY ux_mf_photo_dedup (UF_DEDUP_KEY)');
        $this->index('ix_mf_photo_shoot', 'KEY ix_mf_photo_shoot (UF_SHOOT_ID,UF_GROUP_ID,ID)');
        $this->index('ix_mf_photo_jobs', 'KEY ix_mf_photo_jobs (UF_JOB_STATE,UF_STATUS,ID)');
        $this->constraint('fk_mf_photo_shoot', 'FOREIGN KEY (UF_SHOOT_ID) REFERENCES b_hlbd_mf_shoot(ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->constraint('fk_mf_photo_group', 'FOREIGN KEY (UF_GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->constraint('fk_mf_photo_original_group', 'FOREIGN KEY (UF_ORIGINAL_GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->constraint('fk_mf_photo_existing', 'FOREIGN KEY (UF_EXISTING_PHOTO_ID) REFERENCES b_hlbd_mf_photo(ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->constraint('ck_mf_photo_values', 'CHECK (CHAR_LENGTH(UF_PUBLIC_ID)=36 AND UF_SHOOT_ID>0 AND UF_GROUP_ID>0 AND UF_ORIGINAL_GROUP_ID>0 AND UF_BYTES>0 AND UF_BYTES<=26214400 AND UF_WIDTH>0 AND UF_HEIGHT>0 AND UF_WIDTH*UF_HEIGHT<=40000000 AND CHAR_LENGTH(UF_FINGERPRINT)=64 AND UF_REVISION>0)');
        $this->constraint('ck_mf_photo_state', "CHECK ((UF_STATUS='processing' AND UF_JOB_STATE IN ('pending','published') AND UF_DEDUP_KEY IS NOT NULL AND UF_ORIGINAL_PATH IS NOT NULL AND UF_EXISTING_PHOTO_ID IS NULL) OR (UF_STATUS='ready' AND UF_JOB_STATE='done' AND UF_DEDUP_KEY IS NOT NULL AND UF_ORIGINAL_PATH IS NOT NULL AND UF_THUMB_SRC IS NOT NULL AND UF_PREVIEW_SRC IS NOT NULL AND UF_EXISTING_PHOTO_ID IS NULL) OR (UF_STATUS='failed' AND UF_JOB_STATE='failed' AND UF_DEDUP_KEY IS NOT NULL AND UF_ORIGINAL_PATH IS NOT NULL AND UF_ERROR_CODE IS NOT NULL AND UF_EXISTING_PHOTO_ID IS NULL) OR (UF_STATUS='duplicate' AND UF_JOB_STATE='done' AND UF_DEDUP_KEY IS NULL AND UF_ORIGINAL_PATH IS NULL AND UF_EXISTING_PHOTO_ID IS NOT NULL))");
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists('b_hlbd_mf_photo')
            && false !== $connection->query('SELECT ID FROM b_hlbd_mf_photo LIMIT 1')->fetch()) {
            throw new \RuntimeException('D1 photos exist; destructive rollback is forbidden.');
        }
        $this->getHelperManager()->Hlblock()->deleteHlblockIfExists('MfPhoto');
    }

    private function field(int $id, string $name, string $type, bool $required): void
    {
        $this->getHelperManager()->Hlblock()->saveField($id, [
            'FIELD_NAME' => $name,
            'USER_TYPE_ID' => $type,
            'MANDATORY' => $required ? 'Y' : 'N',
            'MULTIPLE' => 'N',
            'SETTINGS' => 'datetime' === $type ? ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y'] : [],
        ]);
    }

    private function columnType(string $table, string $column): string
    {
        $row = Application::getConnection()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$column}'")->fetch();
        $type = false === $row ? '' : strtolower((string)$row['COLUMN_TYPE']);
        if (1 !== preg_match('/^(?:big)?int(?:\([0-9]+\))?(?: unsigned)?$/D', $type)) {
            throw new \RuntimeException('Unsupported integer type for ' . $table . '.' . $column);
        }

        return $type;
    }

    private function index(string $name, string $definition): void
    {
        $connection = Application::getConnection();
        if (false === $connection->query("SHOW INDEX FROM b_hlbd_mf_photo WHERE Key_name='{$name}'")->fetch()) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_photo ADD ' . $definition);
        }
    }

    private function constraint(string $name, string $definition): void
    {
        $connection = Application::getConnection();
        if (false === $connection->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_photo' AND CONSTRAINT_NAME='{$name}'")->fetch()) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_photo ADD CONSTRAINT ' . $name . ' ' . $definition);
        }
    }
}
