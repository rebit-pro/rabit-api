<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Id;

use Morefoto\Payment\Application\Payment\Contract\PaymentIdGeneratorInterface;

final readonly class PaymentIdGenerator implements PaymentIdGeneratorInterface
{
    public function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
