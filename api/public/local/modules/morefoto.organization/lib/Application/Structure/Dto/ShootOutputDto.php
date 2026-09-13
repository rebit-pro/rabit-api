<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class ShootOutputDto implements ResponseDtoInterface
{
    public function __construct(public string $id, public string $institutionId, public string $name, public ?string $date, public int $revision) {}
}
