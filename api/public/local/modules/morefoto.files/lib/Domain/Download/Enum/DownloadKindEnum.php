<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Download\Enum;

enum DownloadKindEnum: string
{
    case FILE = 'file';
    case ZIP = 'zip';
}
