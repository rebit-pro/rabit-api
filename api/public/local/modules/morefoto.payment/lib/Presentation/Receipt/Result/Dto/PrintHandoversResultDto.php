<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PrintHandoversResultDto implements ResultDtoInterface
{
    /**
     * @param list<PrintHandoverOrderResultDto>              $orders
     * @param array{waiting: int, handed: int, blocked: int} $summary
     */
    public function __construct(
        public string $groupId,
        public string $groupName,
        public bool $enabled,
        public array $orders,
        public array $summary,
    ) {}
}
