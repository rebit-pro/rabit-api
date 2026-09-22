<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\ValueObject;

/** Проверенные фильтры служебного поиска; область сотрудника: null — весь проект, [] — ничего. */
final readonly class OrderSearchCriteria
{
    /** @param null|list<int> $institutionScope */
    public function __construct(
        public ?array $institutionScope,
        public ?string $query = null,
        public ?string $institutionId = null,
        public ?string $shootId = null,
        public ?string $groupId = null,
        public ?string $paymentStatus = null,
        public ?string $productionStatus = null,
        public ?\DateTimeImmutable $createdFrom = null,
        public ?\DateTimeImmutable $createdBefore = null,
    ) {}
}
