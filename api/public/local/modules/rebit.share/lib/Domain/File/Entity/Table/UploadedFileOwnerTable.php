<?php

declare(strict_types=1);

namespace Rebit\Share\Domain\File\Entity\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class UploadedFileOwnerTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'rebit_share_uploaded_file_owner';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('FILE_ID'))->configurePrimary(),
            (new IntegerField('USER_ID'))->configureRequired(),
            (new StringField('MODULE_ID'))->configureRequired(),
            (new DatetimeField('CREATED_AT'))->configureRequired(),
        ];
    }
}
