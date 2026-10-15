<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Dto;

final readonly class OrderEmailTextOutputDto
{
    public function __construct(
        public string $subject,
        public string $body,
        public string $html,
    ) {}
}
