<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Dto\Metadata;

final readonly class DtoClassMetadata
{
    /**
     * @param array<string, true>                 $allowedKeys
     * @param array<string, DtoParameterMetadata> $parameters
     * @param array<string, string>               $serializedMap
     */
    public function __construct(
        public array $allowedKeys,
        public array $parameters,
        public array $serializedMap,
        public bool $hasConstraints,
    ) {}

    public static function createEmpty(): self
    {
        return new self(
            allowedKeys: [],
            parameters: [],
            serializedMap: [],
            hasConstraints: false,
        );
    }
}
