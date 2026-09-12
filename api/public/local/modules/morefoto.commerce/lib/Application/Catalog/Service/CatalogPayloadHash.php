<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Service;

use Morefoto\Commerce\Application\Catalog\Dto\ProductInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\UpdateProductInputDto;

final readonly class CatalogPayloadHash
{
    public function create(ProductInputDto $input): string
    {
        return $this->hash(get_object_vars($input->details));
    }

    public function update(UpdateProductInputDto $input): string
    {
        $fields = get_object_vars($input);
        unset($fields['id']);
        // An omitted PATCH field differs from an explicit empty string, zero or false.
        $fields = array_filter($fields, static fn(mixed $value): bool => null !== $value);

        return $this->hash($fields);
    }

    /** @param array<string, mixed> $fields */
    private function hash(array $fields): string
    {
        ksort($fields, SORT_STRING);

        return hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
