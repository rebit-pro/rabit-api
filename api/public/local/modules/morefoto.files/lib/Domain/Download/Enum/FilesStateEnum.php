<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Download\Enum;

/** Состояние права на файлы заказа; refund и revoked появятся вместе с возвратами I2/I3. */
enum FilesStateEnum: string
{
    case UNPAID = 'unpaid';
    case REVIEW = 'review';
    case EMPTY = 'empty';
    case EXPIRED = 'expired';
    case AVAILABLE = 'available';
}
