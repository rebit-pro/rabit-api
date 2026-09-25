<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Document\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class SellerResultDto implements ResultDtoInterface
{
    public function __construct(
        public bool $published,
        public ?string $name,
        public ?string $inn,
        public ?string $ogrnip,
        public ?string $address,
        public ?string $email,
        public ?string $phone,
    ) {}
}
