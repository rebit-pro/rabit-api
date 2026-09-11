<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Catalog\Enum;

enum ProductKind: string
{
    case PHYSICAL = 'physical';
    case DIGITAL = 'digital';
    case BUNDLE = 'bundle';
}
