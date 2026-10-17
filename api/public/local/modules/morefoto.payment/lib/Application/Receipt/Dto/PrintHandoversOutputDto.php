<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Dto;

final readonly class PrintHandoversOutputDto
{
    /** @param list<PrintHandoverOrderOutputDto> $orders в порядке оплаты */
    public function __construct(
        public string $groupId,
        public string $groupName,
        public bool $enabled,
        public array $orders,
        public int $waiting,
        public int $handed,
        public int $blocked,
    ) {}
}
