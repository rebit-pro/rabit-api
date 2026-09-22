<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Order;

use Morefoto\Commerce\Application\Order\Contract\OrderTokenGeneratorInterface;
use Ramsey\Uuid\Uuid;

final readonly class OrderTokenGenerator implements OrderTokenGeneratorInterface
{
    public function uuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    public function secret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
