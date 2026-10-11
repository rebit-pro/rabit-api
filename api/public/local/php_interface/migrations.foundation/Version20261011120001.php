<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261011120001 extends Version
{
    protected $author = 'claude';
    protected $description = 'E7: digital sales deadline of a group (T0+14, never before print), filled from the original T0';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('b_hlbd_mf_group')) {
            throw new \RuntimeException('E7 sales windows require the merged C3 group calendar.');
        }
        if (!$this->columnExists('UF_DIGITAL_SALES_UNTIL')) {
            $helper = $this->getHelperManager()->Hlblock();
            $helper->saveField($helper->getHlblockId('MfGroup'), [
                'FIELD_NAME' => 'UF_DIGITAL_SALES_UNTIL',
                'USER_TYPE_ID' => 'datetime',
                'MANDATORY' => 'N',
                'MULTIPLE' => 'N',
                'SETTINGS' => ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y'],
            ]);
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_group MODIFY UF_DIGITAL_SALES_UNTIL DATETIME NULL');
        }
        // Moscow keeps UTC+3 all year, so 14 days in UTC are 14 calendar days in Moscow. Print and delivery stay as they are.
        $connection->queryExecute('UPDATE b_hlbd_mf_group SET UF_DIGITAL_SALES_UNTIL=GREATEST(DATE_ADD(UF_SENT_AT, INTERVAL 14 DAY), UF_CLOSES_AT)'
            . ' WHERE UF_SENT_AT IS NOT NULL AND UF_DIGITAL_SALES_UNTIL IS NULL');
        if (!$this->constraintExists('ck_mf_group_digital_sales')) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_group ADD CONSTRAINT ck_mf_group_digital_sales CHECK ('
                . '(UF_SENT_AT IS NULL AND UF_DIGITAL_SALES_UNTIL IS NULL)'
                . ' OR (UF_SENT_AT IS NOT NULL AND UF_DIGITAL_SALES_UNTIL IS NOT NULL AND UF_DIGITAL_SALES_UNTIL>=UF_CLOSES_AT))');
        }
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        // Code before E7 writes calendars without the digital deadline; the column is derived from T0 and can be refilled.
        if ($this->constraintExists('ck_mf_group_digital_sales')) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_group DROP CHECK ck_mf_group_digital_sales');
        }
        if ($this->columnExists('UF_DIGITAL_SALES_UNTIL')) {
            $this->getHelperManager()->Hlblock()->deleteField('MfGroup', 'UF_DIGITAL_SALES_UNTIL');
        }
    }

    private function columnExists(string $column): bool
    {
        return false !== Application::getConnection()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_group' AND COLUMN_NAME='{$column}'")->fetch();
    }

    private function constraintExists(string $name): bool
    {
        return false !== Application::getConnection()->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_group' AND CONSTRAINT_NAME='{$name}'")->fetch();
    }
}
