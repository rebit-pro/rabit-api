<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Dto\Metadata;

final readonly class DtoParameterMetadata
{
    public function __construct(
        public DtoParamTypeEnum $type,
        public bool $nullable,
        public bool $hasDefault,
        public mixed $default,
        public ?string $className,
    ) {}
}
