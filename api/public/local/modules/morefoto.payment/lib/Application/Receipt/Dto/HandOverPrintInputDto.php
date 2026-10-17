<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Dto;

final readonly class HandOverPrintInputDto
{
    /** @param non-empty-list<string> $orderIds публичные ID заказов группы */
    public function __construct(
        public string $groupId,
        public array $orderIds,
        public bool $confirmed,
    ) {}
}
