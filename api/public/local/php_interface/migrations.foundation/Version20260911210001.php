<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

final class Version20260911210001 extends Version
{
    protected $author = 'codex';
    protected $description = 'C1: independent institution records; no Access assignments or HTTP routes';

    public function up(): void
    {
        $helper = $this->getHelperManager()->Hlblock();
        $id = $helper->saveHlblock(['NAME' => 'MfInstitution', 'TABLE_NAME' => 'b_hlbd_mf_institution']);
        /** @var array<string, array{type: string, settings: array<string, mixed>}> $fields */
        $fields = [
            'UF_PUBLIC_ID' => ['type' => 'string', 'settings' => ['MAX_LENGTH' => 36]],
            'UF_NAME' => ['type' => 'string', 'settings' => ['MAX_LENGTH' => 255]],
            'UF_ADDRESS' => ['type' => 'string', 'settings' => ['MAX_LENGTH' => 500]],
            'UF_REVISION' => ['type' => 'integer', 'settings' => ['MIN_VALUE' => 1, 'DEFAULT_VALUE' => 1]],
            'UF_CREATED_AT' => ['type' => 'datetime', 'settings' => ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y']],
            'UF_UPDATED_AT' => ['type' => 'datetime', 'settings' => ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y']],
        ];
        foreach ($fields as $name => $field) {
            $helper->saveField($id, [
                'FIELD_NAME' => $name, 'USER_TYPE_ID' => $field['type'], 'MANDATORY' => 'UF_ADDRESS' === $name ? 'N' : 'Y',
                'MULTIPLE' => 'N', 'SETTINGS' => $field['settings'],
            ]);
        }
        $connection = Application::getConnection();
        $connection->queryExecute(<<<'SQL'
ALTER TABLE b_hlbd_mf_institution ENGINE=InnoDB,
    MODIFY UF_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY UF_NAME VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY UF_ADDRESS VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    MODIFY UF_REVISION INT NOT NULL DEFAULT 1,
    MODIFY UF_CREATED_AT DATETIME NOT NULL,
    MODIFY UF_UPDATED_AT DATETIME NOT NULL
SQL);
        foreach ([
            'ux_mf_institution_public' => 'UNIQUE KEY ux_mf_institution_public (UF_PUBLIC_ID)',
            'ix_mf_institution_created' => 'KEY ix_mf_institution_created (UF_CREATED_AT, ID)',
            'ix_mf_institution_name' => 'KEY ix_mf_institution_name (UF_NAME, ID)',
        ] as $name => $definition) {
            if (false === $connection->query("SHOW INDEX FROM b_hlbd_mf_institution WHERE Key_name='{$name}'")->fetch()) {
                $connection->queryExecute('ALTER TABLE b_hlbd_mf_institution ADD ' . $definition);
            }
        }
        if (false === $connection->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_institution' AND CONSTRAINT_NAME='ck_mf_institution_values'")->fetch()) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_institution ADD CONSTRAINT ck_mf_institution_values CHECK (UF_REVISION > 0 AND CHAR_LENGTH(TRIM(UF_NAME)) > 0 AND CHAR_LENGTH(UF_PUBLIC_ID)=36 AND UF_PUBLIC_ID=LOWER(UF_PUBLIC_ID))');
        }
        require_once __DIR__ . '/../../modules/morefoto.organization/install/index.php';
        (new \Morefoto_Organization())->DoInstall();
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists('b_hlbd_mf_institution')
            && false !== $connection->query('SELECT ID FROM b_hlbd_mf_institution LIMIT 1')->fetch()) {
            throw new \RuntimeException('Institution records exist; preserve data when rolling back application code.');
        }
        ModuleManager::unRegisterModule('morefoto.organization');
        $this->getHelperManager()->Hlblock()->deleteHlblockIfExists('MfInstitution');
    }
}
