<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Max\Request\Dto;

use Symfony\Component\Serializer\Annotation\SerializedName;

final readonly class MaxUserRequestDto
{
    public function __construct(
        #[SerializedName('first_name')]
        public string $firstName = '',
        #[SerializedName('last_name')]
        public ?string $lastName = null,
        #[SerializedName('is_bot')]
        public bool $isBot = false,
    ) {}
}
