<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260920100001 extends Version
{
    protected $author = 'codex';
    protected $description = 'D2: versioned photo assignments, stable children and group covers';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_shoot', 'b_hlbd_mf_group', 'b_hlbd_mf_photo'] as $parent) {
            if (!$connection->isTableExists($parent)) {
                throw new \RuntimeException('D2 requires the merged C3 and D1 schema.');
            }
        }
        $shootType = $this->integerType('b_hlbd_mf_shoot', 'ID');
        $groupType = $this->integerType('b_hlbd_mf_group', 'ID');
        $photoType = $this->integerType('b_hlbd_mf_photo', 'ID');
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_media_shoot_state (
            SHOOT_ID {$shootType} NOT NULL,
            REVISION BIGINT UNSIGNED NOT NULL,
            UPDATED_AT DATETIME NOT NULL,
            PRIMARY KEY (SHOOT_ID),
            CONSTRAINT ck_mf_media_shoot_state CHECK (SHOOT_ID>0 AND REVISION>0),
            CONSTRAINT fk_mf_media_state_shoot FOREIGN KEY (SHOOT_ID) REFERENCES b_hlbd_mf_shoot(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_media_child (
            ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            SHOOT_ID {$shootType} NOT NULL,
            GROUP_ID {$groupType} NOT NULL,
            CODE VARCHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            REVISION BIGINT UNSIGNED NOT NULL,
            CREATED_AT DATETIME NOT NULL,
            UPDATED_AT DATETIME NOT NULL,
            PRIMARY KEY (ID),
            UNIQUE KEY ux_mf_media_child_public (PUBLIC_ID),
            UNIQUE KEY ux_mf_media_child_code (SHOOT_ID,GROUP_ID,CODE),
            KEY ix_mf_media_child_group (GROUP_ID,ID),
            CONSTRAINT ck_mf_media_child CHECK (CHAR_LENGTH(PUBLIC_ID)=36 AND SHOOT_ID>0 AND GROUP_ID>0 AND CODE REGEXP '^[A-Z]{1,3}$' AND REVISION>0),
            CONSTRAINT fk_mf_media_child_shoot FOREIGN KEY (SHOOT_ID) REFERENCES b_hlbd_mf_shoot(ID) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_mf_media_child_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_photo_assignment (
            PHOTO_ID {$photoType} NOT NULL,
            CHILD_ID BIGINT UNSIGNED NOT NULL,
            SEQUENCE_NO INT UNSIGNED NOT NULL,
            CREATED_AT DATETIME NOT NULL,
            PRIMARY KEY (PHOTO_ID,CHILD_ID),
            UNIQUE KEY ux_mf_photo_assignment_sequence (CHILD_ID,SEQUENCE_NO),
            KEY ix_mf_photo_assignment_photo (PHOTO_ID),
            CONSTRAINT ck_mf_photo_assignment CHECK (PHOTO_ID>0 AND CHILD_ID>0 AND SEQUENCE_NO>0),
            CONSTRAINT fk_mf_photo_assignment_photo FOREIGN KEY (PHOTO_ID) REFERENCES b_hlbd_mf_photo(ID) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_mf_photo_assignment_child FOREIGN KEY (CHILD_ID) REFERENCES mf_media_child(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_media_group_cover (
            GROUP_ID {$groupType} NOT NULL,
            PHOTO_ID {$photoType} NOT NULL,
            UPDATED_AT DATETIME NOT NULL,
            PRIMARY KEY (GROUP_ID),
            KEY ix_mf_media_cover_photo (PHOTO_ID),
            CONSTRAINT ck_mf_media_group_cover CHECK (GROUP_ID>0 AND PHOTO_ID>0),
            CONSTRAINT fk_mf_media_cover_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_mf_media_cover_photo FOREIGN KEY (PHOTO_ID) REFERENCES b_hlbd_mf_photo(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_media_idempotency (
    ACTOR_ID INT UNSIGNED NOT NULL,
    RESOURCE_KEY VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    IDEMPOTENCY_KEY CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PAYLOAD_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RESULT_JSON TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ACTOR_ID,RESOURCE_KEY,IDEMPOTENCY_KEY),
    KEY ix_mf_media_idempotency_created (CREATED_AT),
    CONSTRAINT ck_mf_media_idempotency CHECK (ACTOR_ID>0 AND CHAR_LENGTH(PAYLOAD_HASH)=64)
) ENGINE=InnoDB
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_photo_assignment', 'mf_media_group_cover', 'mf_media_child', 'mf_media_idempotency'] as $table) {
            if ($connection->isTableExists($table) && false !== $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetch()) {
                throw new \RuntimeException('D2 media data exists; destructive rollback is forbidden.');
            }
        }
        if ($connection->isTableExists('mf_media_shoot_state')
            && false !== $connection->query('SELECT 1 FROM mf_media_shoot_state WHERE REVISION<>1 LIMIT 1')->fetch()) {
            throw new \RuntimeException('D2 media revisions changed; destructive rollback is forbidden.');
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_photo_assignment');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_media_group_cover');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_media_child');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_media_idempotency');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_media_shoot_state');
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
