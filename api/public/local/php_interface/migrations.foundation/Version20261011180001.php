<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261011180001 extends Version
{
    protected $author = 'claude';
    protected $description = 'D4: markup correction of a transmitted group pauses its sales until the correction is finished';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('b_hlbd_mf_group')) {
            throw new \RuntimeException('D4 markup correction requires the merged C3 group calendar.');
        }
        if (!$this->columnExists('UF_MARKUP_CORRECTION_AT')) {
            $helper = $this->getHelperManager()->Hlblock();
            $helper->saveField($helper->getHlblockId('MfGroup'), [
                'FIELD_NAME' => 'UF_MARKUP_CORRECTION_AT',
                'USER_TYPE_ID' => 'datetime',
                'MANDATORY' => 'N',
                'MULTIPLE' => 'N',
                'SETTINGS' => ['USE_TIMEZONE' => 'N', 'USE_SECOND' => 'Y'],
            ]);
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_group MODIFY UF_MARKUP_CORRECTION_AT DATETIME NULL');
        }
        // Only a transmitted group is corrected: before transmission its markup is simply edited.
        if (!$this->constraintExists('ck_mf_group_markup_correction')) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_group ADD CONSTRAINT ck_mf_group_markup_correction CHECK ('
                . 'UF_MARKUP_CORRECTION_AT IS NULL OR UF_SENT_AT IS NOT NULL)');
        }
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (false !== $connection->query('SELECT 1 FROM b_hlbd_mf_group WHERE UF_MARKUP_CORRECTION_AT IS NOT NULL LIMIT 1')->fetch()) {
            throw new \RuntimeException('A markup correction is open: finish it before the rollback, or the paused sales would resume silently.');
        }
        if ($this->constraintExists('ck_mf_group_markup_correction')) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_group DROP CHECK ck_mf_group_markup_correction');
        }
        if ($this->columnExists('UF_MARKUP_CORRECTION_AT')) {
            $this->getHelperManager()->Hlblock()->deleteField('MfGroup', 'UF_MARKUP_CORRECTION_AT');
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
