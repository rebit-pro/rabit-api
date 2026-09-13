<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260913010001 extends Version
{
    protected $author = 'codex';
    protected $description = 'C3: independent shoots and groups, calendar ownership and typed organization history';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_institution', 'b_hlbd_mf_organization_change', 'mf_institution_operation', 'mf_access_state'] as $parent) {
            if (!$connection->isTableExists($parent)) {
                throw new \RuntimeException('C3 requires the merged C2 schema.');
            }
        }
        $helper = $this->getHelperManager()->Hlblock();
        $shoot = (int)$helper->saveHlblock(['NAME' => 'MfShoot', 'TABLE_NAME' => 'b_hlbd_mf_shoot']);
        foreach (['UF_PUBLIC_ID' => 'string', 'UF_INSTITUTION_ID' => 'integer', 'UF_NAME' => 'string', 'UF_DATE' => 'date', 'UF_REVISION' => 'integer', 'UF_CREATED_AT' => 'datetime', 'UF_UPDATED_AT' => 'datetime'] as $name => $type) {
            $this->field($shoot, $name, $type, 'UF_DATE' !== $name);
        }
        $institutionType = $this->idType('b_hlbd_mf_institution');
        $connection->queryExecute("ALTER TABLE b_hlbd_mf_shoot ENGINE=InnoDB,
            MODIFY UF_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            MODIFY UF_INSTITUTION_ID {$institutionType} NOT NULL,
            MODIFY UF_NAME VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            MODIFY UF_DATE DATE NULL,
            MODIFY UF_REVISION INT NOT NULL,
            MODIFY UF_CREATED_AT DATETIME NOT NULL,
            MODIFY UF_UPDATED_AT DATETIME NOT NULL");
        $this->index('b_hlbd_mf_shoot', 'ux_mf_shoot_public', 'UNIQUE KEY ux_mf_shoot_public (UF_PUBLIC_ID)');
        $this->index('b_hlbd_mf_shoot', 'ix_mf_shoot_institution', 'KEY ix_mf_shoot_institution (UF_INSTITUTION_ID,UF_CREATED_AT,ID)');
        $this->constraint('b_hlbd_mf_shoot', 'ck_mf_shoot_values', 'CHECK (CHAR_LENGTH(UF_PUBLIC_ID)=36 AND UF_INSTITUTION_ID>0 AND CHAR_LENGTH(TRIM(UF_NAME))>0 AND UF_REVISION>0)');
        $this->constraint('b_hlbd_mf_shoot', 'fk_mf_shoot_institution', 'FOREIGN KEY (UF_INSTITUTION_ID) REFERENCES b_hlbd_mf_institution(ID) ON DELETE RESTRICT ON UPDATE RESTRICT');

        $group = (int)$helper->saveHlblock(['NAME' => 'MfGroup', 'TABLE_NAME' => 'b_hlbd_mf_group']);
        foreach (['UF_PUBLIC_ID' => 'string', 'UF_SHOOT_ID' => 'integer', 'UF_NAME' => 'string', 'UF_KIND' => 'string', 'UF_TIMEZONE' => 'string', 'UF_SENT_AT' => 'datetime', 'UF_CLOSES_AT' => 'datetime', 'UF_DELIVERY_DUE_AT' => 'datetime', 'UF_REVISION' => 'integer', 'UF_CREATED_AT' => 'datetime', 'UF_UPDATED_AT' => 'datetime'] as $name => $type) {
            $this->field($group, $name, $type, !in_array($name, ['UF_SENT_AT', 'UF_CLOSES_AT', 'UF_DELIVERY_DUE_AT'], true));
        }
        $shootType = $this->idType('b_hlbd_mf_shoot');
        $connection->queryExecute("ALTER TABLE b_hlbd_mf_group ENGINE=InnoDB,
            MODIFY UF_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            MODIFY UF_SHOOT_ID {$shootType} NOT NULL,
            MODIFY UF_NAME VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            MODIFY UF_KIND VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            MODIFY UF_TIMEZONE VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Europe/Moscow',
            MODIFY UF_SENT_AT DATETIME NULL,
            MODIFY UF_CLOSES_AT DATETIME NULL,
            MODIFY UF_DELIVERY_DUE_AT DATETIME NULL,
            MODIFY UF_REVISION INT NOT NULL,
            MODIFY UF_CREATED_AT DATETIME NOT NULL,
            MODIFY UF_UPDATED_AT DATETIME NOT NULL");
        $this->index('b_hlbd_mf_group', 'ux_mf_group_public', 'UNIQUE KEY ux_mf_group_public (UF_PUBLIC_ID)');
        $this->index('b_hlbd_mf_group', 'ix_mf_group_shoot', 'KEY ix_mf_group_shoot (UF_SHOOT_ID,UF_CREATED_AT,ID)');
        $this->constraint('b_hlbd_mf_group', 'fk_mf_group_shoot', 'FOREIGN KEY (UF_SHOOT_ID) REFERENCES b_hlbd_mf_shoot(ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->constraint('b_hlbd_mf_group', 'ck_mf_group_values', "CHECK (CHAR_LENGTH(UF_PUBLIC_ID)=36 AND UF_SHOOT_ID>0 AND CHAR_LENGTH(TRIM(UF_NAME))>0 AND UF_KIND IN ('regular','staff') AND UF_TIMEZONE='Europe/Moscow' AND UF_REVISION>0)");
        $this->constraint('b_hlbd_mf_group', 'ck_mf_group_calendar', 'CHECK ((UF_SENT_AT IS NULL AND UF_CLOSES_AT IS NULL AND UF_DELIVERY_DUE_AT IS NULL) OR (UF_SENT_AT IS NOT NULL AND UF_CLOSES_AT IS NOT NULL AND UF_DELIVERY_DUE_AT IS NOT NULL AND UF_CLOSES_AT>UF_SENT_AT AND UF_DELIVERY_DUE_AT>UF_CLOSES_AT))');

        $history = (int)$helper->saveHlblock(['NAME' => 'MfOrganizationChange', 'TABLE_NAME' => 'b_hlbd_mf_organization_change']);
        $this->field($history, 'UF_AGGREGATE_TYPE', 'string', true);
        $connection->queryExecute("UPDATE b_hlbd_mf_organization_change SET UF_AGGREGATE_TYPE='institution' WHERE UF_AGGREGATE_TYPE IS NULL OR UF_AGGREGATE_TYPE=''");
        $connection->queryExecute("ALTER TABLE b_hlbd_mf_organization_change MODIFY UF_AGGREGATE_TYPE VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'institution'");
        $columns = [];
        $indexes = $connection->query("SHOW INDEX FROM b_hlbd_mf_organization_change WHERE Key_name='ux_mf_org_change_version'");
        while (false !== ($index = $indexes->fetch())) {
            $columns[(int)$index['Seq_in_index']] = $index['Column_name'];
        }
        ksort($columns);
        if (['UF_AGGREGATE_TYPE', 'UF_AGGREGATE_ID', 'UF_TO_REVISION'] !== array_values($columns)) {
            $drop = [] === $columns ? '' : 'DROP INDEX ux_mf_org_change_version, ';
            // Retain the index name so rerunning C2 cannot recreate the untyped uniqueness constraint.
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_organization_change ' . $drop . 'ADD UNIQUE KEY ux_mf_org_change_version (UF_AGGREGATE_TYPE,UF_AGGREGATE_ID,UF_TO_REVISION)');
        }
        $this->constraint('b_hlbd_mf_organization_change', 'ck_mf_org_change_type', "CHECK (UF_AGGREGATE_TYPE IN ('institution','shoot','group'))");
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_group', 'b_hlbd_mf_shoot'] as $table) {
            if ($connection->isTableExists($table) && false !== $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetch()) {
                throw new \RuntimeException('C3 structure exists: preserve its data and history when rolling back application code.');
            }
        }
        if (false !== $connection->query("SELECT 1 FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE<>'institution' LIMIT 1")->fetch()) {
            throw new \RuntimeException('C3 history exists; destructive rollback is forbidden.');
        }
        $this->getHelperManager()->Hlblock()->deleteHlblockIfExists('MfGroup');
        $this->getHelperManager()->Hlblock()->deleteHlblockIfExists('MfShoot');
        // The additive history discriminator remains compatible with C2 writes through its default.
    }

    private function idType(string $table): string
    {
        $row = Application::getConnection()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='ID'")->fetch();
        $type = strtolower((string)$row['COLUMN_TYPE']);
        if (1 !== preg_match('/^(?:big)?int(?:\([0-9]+\))?(?: unsigned)?$/D', $type)) {
            throw new \RuntimeException('Unsupported native parent ID type.');
        }

        return $type;
    }

    private function field(int $id, string $name, string $type, bool $required): void
    {
        $this->getHelperManager()->Hlblock()->saveField($id, [
            'FIELD_NAME' => $name, 'USER_TYPE_ID' => $type, 'MANDATORY' => $required ? 'Y' : 'N',
            'MULTIPLE' => 'N', 'SETTINGS' => 'datetime' === $type ? ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y'] : [],
        ]);
    }

    private function index(string $table, string $name, string $definition): void
    {
        $connection = Application::getConnection();
        if (false === $connection->query("SHOW INDEX FROM {$table} WHERE Key_name='{$name}'")->fetch()) {
            $connection->queryExecute('ALTER TABLE ' . $table . ' ADD ' . $definition);
        }
    }

    private function constraint(string $table, string $name, string $definition): void
    {
        $connection = Application::getConnection();
        if (false === $connection->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND CONSTRAINT_NAME='{$name}'")->fetch()) {
            $connection->queryExecute('ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $name . ' ' . $definition);
        }
    }
}
