<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260912210001 extends Version
{
    protected $author = 'codex';
    protected $description = 'C2: institution assignments, atomic change journals and idempotency';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_institution', 'b_hlbd_mf_staff_profile', 'mf_access_state'] as $parent) {
            if (!$connection->isTableExists($parent)) {
                throw new \RuntimeException('C2 requires the accepted Access and Institution migrations.');
            }
        }
        $helper = $this->getHelperManager()->Hlblock();
        $id = $helper->saveHlblock(['NAME' => 'MfInstitutionAssignment', 'TABLE_NAME' => 'b_hlbd_mf_institution_assignment']);
        foreach (['UF_INSTITUTION_ID' => 'integer', 'UF_ROLE' => 'string', 'UF_USER_ID' => 'integer', 'UF_CREATED_AT' => 'datetime', 'UF_UPDATED_AT' => 'datetime'] as $name => $type) {
            $this->field((int)$id, $name, $type);
        }
        // Keep the native HL primary-key type: FK columns must match signedness.
        $parentType = strtolower((string)$connection->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_institution' AND COLUMN_NAME='ID'")->fetch()['COLUMN_TYPE']);
        if (1 !== preg_match('/^(?:big)?int(?:\([0-9]+\))?(?: unsigned)?$/D', $parentType)) {
            throw new \RuntimeException('Unsupported native institution ID type: ' . $parentType);
        }
        if (!$this->constraintExists('b_hlbd_mf_institution_assignment', 'fk_mf_assignment_institution')) {
            $connection->queryExecute("ALTER TABLE b_hlbd_mf_institution_assignment ENGINE=InnoDB,
                MODIFY UF_INSTITUTION_ID {$parentType} NOT NULL,
                MODIFY UF_ROLE VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                MODIFY UF_USER_ID INT NOT NULL,
                MODIFY UF_CREATED_AT DATETIME NOT NULL,
                MODIFY UF_UPDATED_AT DATETIME NOT NULL");
        }
        $this->index('b_hlbd_mf_institution_assignment', 'ux_mf_assignment_slot', 'UNIQUE KEY ux_mf_assignment_slot (UF_INSTITUTION_ID,UF_ROLE)');
        $this->index('b_hlbd_mf_institution_assignment', 'ix_mf_assignment_user', 'KEY ix_mf_assignment_user (UF_USER_ID,UF_ROLE,UF_INSTITUTION_ID)');
        $this->constraint('b_hlbd_mf_institution_assignment', 'ck_mf_assignment_values', "CHECK (UF_INSTITUTION_ID>0 AND UF_USER_ID>0 AND UF_ROLE IN ('curator','head'))");
        $this->constraint('b_hlbd_mf_institution_assignment', 'fk_mf_assignment_institution', 'FOREIGN KEY (UF_INSTITUTION_ID) REFERENCES b_hlbd_mf_institution(ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->constraint('b_hlbd_mf_institution_assignment', 'fk_mf_assignment_staff', 'FOREIGN KEY (UF_USER_ID) REFERENCES b_hlbd_mf_staff_profile(UF_USER_ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
        foreach (['MfAccessChange' => 'b_hlbd_mf_access_change', 'MfOrganizationChange' => 'b_hlbd_mf_organization_change'] as $name => $table) {
            $id = $helper->saveHlblock(['NAME' => $name, 'TABLE_NAME' => $table]);
            foreach (['UF_AGGREGATE_ID' => 'integer', 'UF_FROM_REVISION' => 'integer', 'UF_TO_REVISION' => 'integer', 'UF_ACTOR_ID' => 'integer', 'UF_OPERATION_ID' => 'string', 'UF_DELTA' => 'string', 'UF_OCCURRED_AT' => 'datetime'] as $field => $type) {
                $this->field((int)$id, $field, $type);
            }
            $aggregateType = 'MfOrganizationChange' === $name ? $parentType : 'INT';
            $connection->queryExecute("ALTER TABLE {$table} ENGINE=InnoDB,
                MODIFY UF_AGGREGATE_ID {$aggregateType} NOT NULL,
                MODIFY UF_FROM_REVISION INT NOT NULL,
                MODIFY UF_TO_REVISION INT NOT NULL,
                MODIFY UF_ACTOR_ID INT NOT NULL,
                MODIFY UF_OPERATION_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                MODIFY UF_DELTA TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                MODIFY UF_OCCURRED_AT DATETIME NOT NULL");
            $prefix = 'MfAccessChange' === $name ? 'mf_access_change' : 'mf_org_change';
            $this->index($table, 'ux_' . $prefix . '_version', 'UNIQUE KEY ux_' . $prefix . '_version (UF_AGGREGATE_ID,UF_TO_REVISION)');
            $this->index($table, 'ix_' . $prefix . '_operation', 'KEY ix_' . $prefix . '_operation (UF_OPERATION_ID)');
            $this->constraint($table, 'ck_' . $prefix . '_values', 'CHECK (UF_AGGREGATE_ID>0 AND UF_FROM_REVISION>=0 AND UF_TO_REVISION=UF_FROM_REVISION+1 AND UF_ACTOR_ID>0 AND CHAR_LENGTH(UF_OPERATION_ID)=36 AND JSON_VALID(UF_DELTA))');
        }
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_institution_operation (
    actor_id INT NOT NULL,
    operation VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idempotency_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    result_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (actor_id,operation,idempotency_key),
    CONSTRAINT ck_mf_institution_operation CHECK (
        actor_id>0 AND CHAR_LENGTH(operation)>0
        AND idempotency_key REGEXP '^[a-f0-9]{32}$'
        AND payload_hash REGEXP '^[a-f0-9]{64}$'
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_institution_assignment', 'b_hlbd_mf_access_change', 'b_hlbd_mf_organization_change', 'mf_institution_operation'] as $table) {
            if ($connection->isTableExists($table) && false !== $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetch()) {
                throw new \RuntimeException('C2 records, history or operations exist; preserve data when rolling back application code.');
            }
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_institution_operation');
        foreach (['MfInstitutionAssignment', 'MfAccessChange', 'MfOrganizationChange'] as $name) {
            $this->getHelperManager()->Hlblock()->deleteHlblockIfExists($name);
        }
    }

    private function field(int $id, string $name, string $type): void
    {
        $settings = 'datetime' === $type ? ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y'] : [];
        $this->getHelperManager()->Hlblock()->saveField($id, [
            'FIELD_NAME' => $name, 'USER_TYPE_ID' => $type, 'MANDATORY' => 'Y',
            'MULTIPLE' => 'N', 'SETTINGS' => $settings,
        ]);
    }

    private function index(string $table, string $name, string $definition): void
    {
        $connection = Application::getConnection();
        if (false === $connection->query("SHOW INDEX FROM {$table} WHERE Key_name='{$name}'")->fetch()) {
            $connection->queryExecute('ALTER TABLE ' . $table . ' ADD ' . $definition);
        }
    }

    private function constraintExists(string $table, string $name): bool
    {
        return false !== Application::getConnection()->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND CONSTRAINT_NAME='{$name}'")->fetch();
    }

    private function constraint(string $table, string $name, string $definition): void
    {
        if (!$this->constraintExists($table, $name)) {
            Application::getConnection()->queryExecute('ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $name . ' ' . $definition);
        }
    }
}
