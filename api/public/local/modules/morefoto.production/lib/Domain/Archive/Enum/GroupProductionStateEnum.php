<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\Enum;

enum GroupProductionStateEnum: string
{
    /** Приём печати ещё идёт или ссылка группы не передана. */
    case OPEN = 'open';
    /** Печать закрыта после выкладки L1: версия 1 соберётся автоматически. */
    case SCHEDULED = 'scheduled';
    /** Печать закрыта до выкладки L1: архив собирает организатор (L1-DEC-08). */
    case MANUAL = 'manual';
    case BUILDING = 'building';
    case READY = 'ready';
    case FAILED = 'failed';
    case EMPTY = 'empty';
    case EXPIRED = 'expired';
}
