<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Download\Enum;

enum DownloadStatusEnum: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
}
