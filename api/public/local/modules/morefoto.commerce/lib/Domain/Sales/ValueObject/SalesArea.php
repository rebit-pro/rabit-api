<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Sales\ValueObject;

/** Область отчёта продаж: ограничения сотрудника из Access и выбранный уровень по публичным ID. */
final readonly class SalesArea
{
    /**
     * @param null|list<int> $institutionIds native учреждения сотрудника; null — без ограничения
     * @param null|list<int> $groupIds       native группы сотрудника; null — без ограничения
     */
    public function __construct(
        public ?array $institutionIds,
        public ?array $groupIds,
        public ?string $institutionId = null,
        public ?string $shootId = null,
        public ?string $groupId = null,
    ) {}
}
