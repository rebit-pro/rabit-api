<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Request;

use Morefoto\Commerce\Application\Conditions\Dto\ProductConditionInputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Domain\Catalog\Exception\MalformedCatalogJsonException;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;

final readonly class ConditionsRequestFactory
{
    /** @param array<string, mixed> $query */
    public function read(string $raw, array $query): void
    {
        if ('' !== trim($raw) || [] !== $query) {
            throw new InvalidConditionsException('GET conditions must not contain a body or query parameters.');
        }
    }

    /** @param array<string, mixed> $query */
    public function save(string $raw, string $contentType, string $key, array $query, bool $group): ConditionsRequestDto
    {
        if ([] !== $query) {
            throw new InvalidConditionsException('Mutation parameters must be supplied only in JSON.');
        }
        $contentType = strtolower(trim(explode(';', $contentType)[0]));
        if ('application/json' !== $contentType || 262144 < strlen($raw)) {
            throw new InvalidConditionsException('Expected application/json body up to 262144 bytes.');
        }
        try {
            $decoded = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new MalformedCatalogJsonException('Malformed JSON body.', 0, $exception);
        }
        if (!$decoded instanceof \stdClass) {
            throw new InvalidConditionsException('JSON body must be an object.');
        }
        $data = get_object_vars($decoded);
        $fields = ['catalogRevision', 'giftEnabled', 'giftForStaff', 'giftThreshold', 'products', 'revision'];
        if ($group) {
            $fields[] = 'conditionsRevision';
            $fields[] = 'inherit';
        }
        sort($fields);
        $actual = array_keys($data);
        sort($actual);
        if ($fields !== $actual) {
            throw new InvalidConditionsException('Condition request fields do not match the API contract.');
        }
        foreach (['revision', 'catalogRevision', 'giftThreshold'] as $field) {
            if (!is_int($data[$field])) {
                throw new InvalidConditionsException('Condition revision and money fields must be integers.');
            }
        }
        foreach (['giftEnabled', 'giftForStaff'] as $field) {
            if (!is_bool($data[$field])) {
                throw new InvalidConditionsException('Condition flags must be booleans.');
            }
        }
        if ($group && (!is_int($data['conditionsRevision']) || !is_bool($data['inherit']))) {
            throw new InvalidConditionsException('Group condition version and inheritance have invalid types.');
        }
        if (!is_array($data['products']) || !array_is_list($data['products'])) {
            throw new InvalidConditionsException('Products must be a JSON array.');
        }
        $products = [];
        foreach ($data['products'] as $value) {
            if (!$value instanceof \stdClass) {
                throw new InvalidConditionsException('Every condition product must be an object.');
            }
            $product = get_object_vars($value);
            $names = array_keys($product);
            sort($names);
            if (['active', 'id', 'price', 'staffDiscount'] !== $names || !is_string($product['id']) || !is_int($product['price'])
                || !is_bool($product['active']) || !is_bool($product['staffDiscount'])) {
                throw new InvalidConditionsException('Condition product fields have invalid names or JSON types.');
            }
            $products[] = new ProductConditionInputDto($product['id'], $product['price'], $product['active'], $product['staffDiscount']);
        }
        $input = new SaveConditionsInputDto(
            revision: $data['revision'],
            catalogRevision: $data['catalogRevision'],
            products: $products,
            giftEnabled: $data['giftEnabled'],
            giftThreshold: $data['giftThreshold'],
            giftForStaff: $data['giftForStaff'],
            conditionsRevision: $group ? $data['conditionsRevision'] : null,
            inherit: $group && $data['inherit'],
        );

        return new ConditionsRequestDto($input, new IdempotencyKey($key));
    }
}
