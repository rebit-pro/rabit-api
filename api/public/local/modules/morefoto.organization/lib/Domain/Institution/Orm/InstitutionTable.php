<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Institution\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\DatetimeField;

final class InstitutionTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_hlbd_mf_institution';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            new StringField('UF_PUBLIC_ID'),
            new StringField('UF_NAME'),
            new StringField('UF_ADDRESS'),
            new IntegerField('UF_REVISION'),
            new DatetimeField('UF_CREATED_AT'),
            new DatetimeField('UF_UPDATED_AT'),
        ];
    }
}
