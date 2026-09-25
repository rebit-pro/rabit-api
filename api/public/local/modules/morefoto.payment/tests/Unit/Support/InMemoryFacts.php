<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit\Support;

use Morefoto\Payment\Domain\Payment\Repository\PaymentFactRepositoryInterface;

/** @phpstan-import-type FactRecord from PaymentFactRepositoryInterface */
final class InMemoryFacts implements PaymentFactRepositoryInterface
{
    /** @var array<string, FactRecord> */
    public array $rows = [];

    public function insert(array $fact): bool
    {
        $key = $fact['PROVIDER'] . ':' . $fact['PROVIDER_PAYMENT_ID'];
        if (isset($this->rows[$key])) {
            return false;
        }
        $this->rows[$key] = $fact;

        return true;
    }

    public function forOrder(int $orderId): array
    {
        return array_values(array_filter($this->rows, static fn(array $fact): bool => $fact['ORDER_ID'] === $orderId));
    }
}
