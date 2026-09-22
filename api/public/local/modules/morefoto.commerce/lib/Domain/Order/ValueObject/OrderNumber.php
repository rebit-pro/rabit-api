<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\ValueObject;

/** Отображаемый номер MF: не менее шести цифр внутреннего счётчика, не даёт доступа к заказу. */
final readonly class OrderNumber
{
    public string $value;

    public function __construct(int $sequence)
    {
        if (1 > $sequence) {
            throw new \InvalidArgumentException('Order sequence must be positive.');
        }
        $this->value = sprintf('MF-%06d', $sequence);
    }
}
