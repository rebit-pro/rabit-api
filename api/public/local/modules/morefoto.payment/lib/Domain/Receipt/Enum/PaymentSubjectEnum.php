<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\Enum;

/** Признак предмета расчёта (тег 1212), допустимый для продажи фотографий; выбирается в настройках (G2-DEC-03). */
enum PaymentSubjectEnum: string
{
    case COMMODITY = 'commodity';
    case JOB = 'job';
    case SERVICE = 'service';
    case INTELLECTUAL_ACTIVITY = 'intellectual_activity';
    case PROPERTY_RIGHT = 'property_right';
    case ANOTHER = 'another';
}
