<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class SalesReadinessOutputDto
{
    public function __construct(
        public int $activeProducts,
        public string $fingerprint,
    ) {}
}
