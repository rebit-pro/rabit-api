<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class RequestHeader
{
    public function __construct(
        public string $name,
        public bool $required = true,
    ) {}
}
