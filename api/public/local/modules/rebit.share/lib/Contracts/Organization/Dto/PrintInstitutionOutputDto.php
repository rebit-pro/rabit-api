<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class PrintInstitutionOutputDto
{
    public function __construct(public int $nativeId, public string $id, public string $name) {}
}
