<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class VisibleInstitutionOutputDto implements ResponseDtoInterface
{
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public int $revision,
        public ?int $curatorId,
        public ?int $headId,
    ) {}
}
