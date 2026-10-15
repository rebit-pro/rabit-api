<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Dto;

final readonly class DeliveryReportOutputDto
{
    public function __construct(
        public int $created,
        public int $queued,
        public int $cancelled,
    ) {}
}
