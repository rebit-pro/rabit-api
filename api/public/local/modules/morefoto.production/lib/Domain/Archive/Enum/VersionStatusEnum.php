<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\Enum;

enum VersionStatusEnum: string
{
    /** Ждёт сборки или повтора. */
    case PENDING = 'pending';
    /** Архив собран, ссылка действует до срока хранения. */
    case READY = 'ready';
    /** Сборка не удалась после всех попыток или без шанса на повтор. */
    case FAILED = 'failed';
    /** Оплаченной печати нет: архив не создаётся. */
    case EMPTY = 'empty';
    /** Срок хранения прошёл: файл удалён, состав остаётся. */
    case EXPIRED = 'expired';
}
