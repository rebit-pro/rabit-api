<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Catalog\Enum;

enum ProductKind: string
{
    case PHYSICAL = 'physical';
    case DIGITAL = 'digital';
    case BUNDLE = 'bundle';

    public function allowsQuantity(bool $allowDigitalQuantity): bool
    {
        return self::PHYSICAL === $this || (self::DIGITAL === $this && $allowDigitalQuantity);
    }
}
