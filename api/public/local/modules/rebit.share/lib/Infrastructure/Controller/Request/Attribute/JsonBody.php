<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class JsonBody
{
    public function __construct(
        public int $maxBytes = 32768,
    ) {}
}
