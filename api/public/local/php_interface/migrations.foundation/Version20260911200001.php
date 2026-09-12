<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

final class Version20260911200001 extends Version
{
    protected $author = 'codex';
    protected $description = 'W06: MoreFoto staff HL profile, serialized access state and module installation';

    public function up(): void
    {
        $helper = $this->getHelperManager()->Hlblock();
        $id = $helper->saveHlblock([
            'NAME' => 'MfStaffProfile',
            'TABLE_NAME' => 'b_hlbd_mf_staff_profile',
        ]);
        /** @var array<string, array{type: string, settings: array<string, mixed>}> $fields */
        $fields = [
            'UF_USER_ID' => ['type' => 'integer', 'settings' => ['MIN_VALUE' => 1]],
            'UF_ROLE' => ['type' => 'string', 'settings' => ['MAX_LENGTH' => 16]],
            'UF_ACTIVE' => ['type' => 'boolean', 'settings' => ['DEFAULT_VALUE' => 0]],
            'UF_REVISION' => ['type' => 'integer', 'settings' => ['MIN_VALUE' => 1, 'DEFAULT_VALUE' => 1]],
            'UF_ACCESS_REVISION' => ['type' => 'integer', 'settings' => ['MIN_VALUE' => 1, 'DEFAULT_VALUE' => 1]],
            'UF_CREATED_AT' => ['type' => 'datetime', 'settings' => ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y']],
            'UF_UPDATED_AT' => ['type' => 'datetime', 'settings' => ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y']],
        ];
        foreach ($fields as $name => $field) {
            $helper->saveField($id, [
                'FIELD_NAME' => $name,
                'USER_TYPE_ID' => $field['type'],
                'MANDATORY' => 'Y',
                'MULTIPLE' => 'N',
                'SETTINGS' => $field['settings'],
            ]);
        }
        $connection = Application::getConnection();
        $connection->queryExecute(<<<'SQL'
ALTER TABLE b_hlbd_mf_staff_profile ENGINE=InnoDB,
    MODIFY UF_USER_ID INT NOT NULL,
    MODIFY UF_ROLE VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY UF_ACTIVE TINYINT NOT NULL DEFAULT 0,
    MODIFY UF_REVISION INT NOT NULL DEFAULT 1,
    MODIFY UF_ACCESS_REVISION INT NOT NULL DEFAULT 1,
    MODIFY UF_CREATED_AT DATETIME NOT NULL,
    MODIFY UF_UPDATED_AT DATETIME NOT NULL
SQL);
        foreach ([
            'ux_mf_staff_user' => 'UNIQUE KEY ux_mf_staff_user (UF_USER_ID)',
            'ix_mf_staff_role_active' => 'KEY ix_mf_staff_role_active (UF_ROLE, UF_ACTIVE, UF_USER_ID)',
        ] as $name => $definition) {
            if (false === $connection->query("SHOW INDEX FROM b_hlbd_mf_staff_profile WHERE Key_name = '{$name}'")->fetch()) {
                $connection->queryExecute('ALTER TABLE b_hlbd_mf_staff_profile ADD ' . $definition);
            }
        }
        if (false === $connection->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'b_hlbd_mf_staff_profile' AND CONSTRAINT_NAME = 'ck_mf_staff_values'")->fetch()) {
            $connection->queryExecute(<<<'SQL'
ALTER TABLE b_hlbd_mf_staff_profile ADD CONSTRAINT ck_mf_staff_values CHECK (
    UF_USER_ID > 0 AND UF_ROLE IN ('organizer', 'curator', 'head', 'teacher')
    AND UF_ACTIVE IN (0, 1) AND UF_REVISION > 0 AND UF_ACCESS_REVISION > 0
)
SQL);
        }
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_access_state (
    id TINYINT NOT NULL PRIMARY KEY,
    assignments_revision BIGINT NOT NULL,
    CONSTRAINT ck_mf_access_state CHECK (id = 1 AND assignments_revision > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $connection->queryExecute('INSERT IGNORE INTO mf_access_state (id, assignments_revision) VALUES (1, 1)');
        require_once __DIR__ . '/../../modules/morefoto.access/install/index.php';
        (new \Morefoto_Access())->DoInstall();
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists('b_hlbd_mf_staff_profile')
            && false !== $connection->query('SELECT ID FROM b_hlbd_mf_staff_profile LIMIT 1')->fetch()) {
            throw new \RuntimeException('Staff profiles exist. Preserve Access data when rolling back application code.');
        }
        if ($connection->isTableExists('mf_access_state')
            && false !== $connection->query('SELECT id FROM mf_access_state WHERE assignments_revision <> 1 LIMIT 1')->fetch()) {
            throw new \RuntimeException('Access state has changed. Schema rollback requires a separate data migration.');
        }
        ModuleManager::unRegisterModule('morefoto.access');
        $this->getHelperManager()->Hlblock()->deleteHlblockIfExists('MfStaffProfile');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_access_state');
    }
}
