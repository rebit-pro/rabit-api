<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class RouteParameter
{
    public function __construct(
        public string $name,
        public ?string $pattern = null,
        public string $errorCode = 'INVALID_ROUTE',
        public int $errorStatus = 400,
    ) {}
}
