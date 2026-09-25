<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\ValueObject;

use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;

/** Итог одной сверки попытки: новое состояние и расписание следующей проверки. Моменты — UTC `Y-m-d H:i:s`. */
final readonly class AttemptOutcome
{
    public function __construct(
        public AttemptStatusEnum $status,
        public string $checkedAt,
        public ?string $nextCheckAt,
        public ?string $providerPaymentId = null,
        public ?string $confirmationUrl = null,
        public ?string $cancelReason = null,
        public ?string $paidAt = null,
        public ?int $incomeAmount = null,
        public bool $latePayment = false,
    ) {}
}
