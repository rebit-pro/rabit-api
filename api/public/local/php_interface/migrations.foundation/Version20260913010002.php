<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260913010002 extends Version
{
    protected $author = 'codex';
    protected $description = 'C3: Access-owned teacher assignments for real Organization groups';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_group', 'b_hlbd_mf_staff_profile', 'mf_access_state', 'b_hlbd_mf_access_change'] as $parent) {
            if (!$connection->isTableExists($parent)) {
                throw new \RuntimeException('C3 group assignments require accepted Organization and Access migrations.');
            }
        }
        $helper = $this->getHelperManager()->Hlblock();
        $id = $helper->saveHlblock(['NAME' => 'MfGroupAssignment', 'TABLE_NAME' => 'b_hlbd_mf_group_assignment']);
        foreach (['UF_GROUP_ID' => 'integer', 'UF_USER_ID' => 'integer', 'UF_CREATED_AT' => 'datetime', 'UF_UPDATED_AT' => 'datetime'] as $name => $type) {
            $helper->saveField((int)$id, [
                'FIELD_NAME' => $name,
                'USER_TYPE_ID' => $type,
                'MANDATORY' => 'Y',
                'MULTIPLE' => 'N',
                'SETTINGS' => 'datetime' === $type ? ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y'] : [],
            ]);
        }
        $groupType = $this->columnType('b_hlbd_mf_group', 'ID');
        $userType = $this->columnType('b_hlbd_mf_staff_profile', 'UF_USER_ID');
        if (!$this->constraintExists('fk_mf_group_assignment_group')) {
            $connection->queryExecute("ALTER TABLE b_hlbd_mf_group_assignment ENGINE=InnoDB,
                MODIFY UF_GROUP_ID {$groupType} NOT NULL,
                MODIFY UF_USER_ID {$userType} NOT NULL,
                MODIFY UF_CREATED_AT DATETIME NOT NULL,
                MODIFY UF_UPDATED_AT DATETIME NOT NULL");
        }
        $this->index('ux_mf_group_assignment_slot', 'UNIQUE KEY ux_mf_group_assignment_slot (UF_GROUP_ID)');
        $this->index('ix_mf_group_assignment_user', 'KEY ix_mf_group_assignment_user (UF_USER_ID,UF_GROUP_ID)');
        $this->constraint('ck_mf_group_assignment_values', 'CHECK (UF_GROUP_ID>0 AND UF_USER_ID>0)');
        $this->constraint('fk_mf_group_assignment_group', 'FOREIGN KEY (UF_GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->constraint('fk_mf_group_assignment_staff', 'FOREIGN KEY (UF_USER_ID) REFERENCES b_hlbd_mf_staff_profile(UF_USER_ID) ON DELETE RESTRICT ON UPDATE RESTRICT');
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists('b_hlbd_mf_group_assignment')
            && false !== $connection->query('SELECT ID FROM b_hlbd_mf_group_assignment LIMIT 1')->fetch()) {
            throw new \RuntimeException('Group assignments exist; preserve data when rolling back application code.');
        }
        $this->getHelperManager()->Hlblock()->deleteHlblockIfExists('MfGroupAssignment');
    }

    private function columnType(string $table, string $column): string
    {
        $row = Application::getConnection()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$column}'")->fetch();
        $type = false === $row ? '' : strtolower((string)$row['COLUMN_TYPE']);
        if (1 !== preg_match('/^(?:big)?int(?:\([0-9]+\))?(?: unsigned)?$/D', $type)) {
            throw new \RuntimeException('Unsupported parent integer type for ' . $table . '.' . $column);
        }

        return $type;
    }

    private function index(string $name, string $definition): void
    {
        $connection = Application::getConnection();
        if (false === $connection->query("SHOW INDEX FROM b_hlbd_mf_group_assignment WHERE Key_name='{$name}'")->fetch()) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_group_assignment ADD ' . $definition);
        }
    }

    private function constraintExists(string $name): bool
    {
        return false !== Application::getConnection()->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_group_assignment' AND CONSTRAINT_NAME='{$name}'")->fetch();
    }

    private function constraint(string $name, string $definition): void
    {
        if (!$this->constraintExists($name)) {
            Application::getConnection()->queryExecute('ALTER TABLE b_hlbd_mf_group_assignment ADD CONSTRAINT ' . $name . ' ' . $definition);
        }
    }
}
