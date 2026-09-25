<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\ValueObject;

/** Проверенные фильтры реестра платежей; область сотрудника: null — весь проект, [] — ничего. */
final readonly class PaymentSearchCriteria
{
    /** @param null|list<int> $institutionScope */
    public function __construct(
        public ?array $institutionScope,
        public ?string $status = null,
        public ?string $orderNumber = null,
        public ?\DateTimeImmutable $createdFrom = null,
        public ?\DateTimeImmutable $createdBefore = null,
        public ?bool $latePayment = null,
    ) {}
}
