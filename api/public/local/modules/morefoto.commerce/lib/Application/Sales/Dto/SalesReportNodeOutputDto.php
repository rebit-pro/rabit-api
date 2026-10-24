<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class SalesReportNodeOutputDto
{
    public function __construct(public string $id, public string $name) {}
}
