<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/** Read model over the migrated HL table; no Objectify objects cross the repository boundary. */
final class StaffProfileTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_hlbd_mf_staff_profile';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary(),
            new IntegerField('UF_USER_ID'),
            new StringField('UF_ROLE'),
            new IntegerField('UF_ACTIVE'),
            new IntegerField('UF_REVISION'),
            new IntegerField('UF_ACCESS_REVISION'),
        ];
    }
}
