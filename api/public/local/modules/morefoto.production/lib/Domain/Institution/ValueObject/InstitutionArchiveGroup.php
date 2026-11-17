<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Institution\ValueObject;

/** Сохранённая область группы и пути строк исходных заданий L1 внутри выпуска учреждения. */
final readonly class InstitutionArchiveGroup
{
    /**
     * @param list<int>             $sourceVersionIds внутренние ID неизменяемых версий L1
     * @param array<string, string> $entries          полный путь ZIP по ID строки заказа
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $deadline,
        public array $sourceVersionIds,
        public array $entries,
    ) {}
}
