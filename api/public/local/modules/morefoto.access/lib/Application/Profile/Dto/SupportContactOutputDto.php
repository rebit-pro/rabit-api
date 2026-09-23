<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Profile\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class SupportContactOutputDto implements ResultDtoInterface
{
    public function __construct(
        public ?string $name,
        public ?string $email,
        public ?string $phone,
    ) {}
}
