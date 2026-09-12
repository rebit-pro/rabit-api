<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class ShootMutationOutputDto implements ResponseDtoInterface
{
    public function __construct(public string $id, public int $revision) {}
}
