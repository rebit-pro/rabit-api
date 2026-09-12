<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Request;

use Morefoto\Commerce\Application\Catalog\Dto\ListProductsInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ProductInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\UpdateProductInputDto;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\Exception\MalformedCatalogJsonException;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;

final readonly class CatalogRequestFactory
{
    private const array TYPES = ['name' => 'string', 'description' => 'string', 'kind' => 'string', 'price' => 'integer', 'printCount' => 'integer', 'format' => 'string', 'unit' => 'string', 'staffDiscount' => 'boolean', 'active' => 'boolean'];

    /** @param array<string, mixed> $query */
    public function create(string $raw, string $contentType, string $key, array $query = []): CreateProductRequestDto
    {
        $data = $this->body($raw, $contentType, $query, false);
        foreach (self::TYPES as $field => $type) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidProductException('Missing product field: ' . $field . '.');
            }
        }
        $input = new ProductInputDto(
            name: $data['name'],
            description: $data['description'],
            kind: $this->kind($data['kind']),
            price: $data['price'],
            printCount: $data['printCount'],
            format: $data['format'],
            unit: $data['unit'],
            staffDiscount: $data['staffDiscount'],
            active: $data['active'],
        );

        return new CreateProductRequestDto($input, new IdempotencyKey($key));
    }

    /** @param array<string, mixed> $query */
    public function update(string $raw, string $contentType, string $key, string $productId, array $query = []): UpdateProductRequestDto
    {
        $data = $this->body($raw, $contentType, $query, true);
        if (!isset($data['revision'])) {
            throw new InvalidProductException('Catalog revision is required.');
        }
        $input = new UpdateProductInputDto(
            id: $productId,
            revision: $data['revision'],
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            kind: isset($data['kind']) ? $this->kind($data['kind']) : null,
            price: $data['price'] ?? null,
            printCount: $data['printCount'] ?? null,
            format: $data['format'] ?? null,
            unit: $data['unit'] ?? null,
            staffDiscount: $data['staffDiscount'] ?? null,
            active: $data['active'] ?? null,
        );

        return new UpdateProductRequestDto($input, new IdempotencyKey($key));
    }

    /** @param array<string, mixed> $query */
    public function list(array $query, string $raw = ''): ListProductsRequestDto
    {
        if ('' !== trim($raw)) {
            throw new InvalidProductException('GET catalogue must not contain a body.');
        }
        $values = [];
        foreach ($query as $name => $value) {
            if (!in_array($name, ['page', 'pageSize'], true) || !is_string($value)
                || 1 !== preg_match('/^[1-9][0-9]*$/D', $value) || (string)(int)$value !== $value) {
                throw new InvalidProductException('Invalid catalogue pagination query.');
            }
            $values[$name] = (int)$value;
        }

        return new ListProductsRequestDto(new ListProductsInputDto($values['page'] ?? 1, $values['pageSize'] ?? 50));
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, bool|int|string>
     */
    private function body(string $raw, string $contentType, array $query, bool $patch): array
    {
        if ([] !== $query) {
            throw new InvalidProductException('Mutation parameters must be supplied only in JSON.');
        }
        $contentType = strtolower(trim(explode(';', $contentType)[0]));
        if ('application/json' !== $contentType || 32768 < strlen($raw)) {
            throw new InvalidProductException('Expected application/json body up to 32768 bytes.');
        }
        try {
            $decoded = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new MalformedCatalogJsonException('Malformed JSON body.');
        }
        if (!$decoded instanceof \stdClass) {
            throw new InvalidProductException('JSON body must be an object.');
        }
        $data = get_object_vars($decoded);
        foreach ($data as $field => $value) {
            $type = $patch && 'revision' === $field ? 'integer' : (self::TYPES[$field] ?? null);
            if (null === $type || gettype($value) !== $type) {
                throw new InvalidProductException('Unknown product field or incorrect JSON type: ' . $field . '.');
            }
        }

        return $data;
    }

    private function kind(string $value): ProductKind
    {
        return ProductKind::tryFrom($value) ?? throw new InvalidProductException('Invalid product kind.');
    }
}
