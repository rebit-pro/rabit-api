<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Domain\LeadHunt\Entity\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * Найденные на внешних площадках заявки: дедупликация по (UF_SOURCE, UF_GUID)
 * и журнал доставки в Telegram.
 */
final class ExternalLeadTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'rebit_leadhunter_external_lead';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            new StringField('UF_SOURCE'),
            new StringField('UF_GUID'),
            new StringField('UF_TITLE'),
            new TextField('UF_DESCRIPTION'),
            new StringField('UF_URL'),
            new StringField('UF_MATCHED_KEYWORDS'),
            new StringField('UF_STATUS'),
            new IntegerField('UF_ATTEMPTS'),
            new DatetimeField('UF_PUBLISHED_AT'),
            new DatetimeField('UF_CREATED_AT'),
            new DatetimeField('UF_UPDATED_AT'),
        ];
    }
}
