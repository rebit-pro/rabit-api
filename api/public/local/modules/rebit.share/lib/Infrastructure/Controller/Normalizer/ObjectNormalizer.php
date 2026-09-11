<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Normalizer;

use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use Rebit\Share\Infrastructure\Controller\Attribute\SkipWhenNull;
use Rebit\Share\Shared\Interface\NormalizerInterface;

/**
 * Класс нормализует объект (в том числе Enum) в массив.
 *
 * Если класс имеет методы __serialize или jsonSerialize, то будут использованы они.
 */
final readonly class ObjectNormalizer implements NormalizerInterface
{
    public function __construct(
        private NormalizerInterface $enumNormalizer,
        private NormalizerInterface $dateTimeNormalizer,
        private NormalizerInterface $dateNormalizer,
        private NormalizerInterface $scalarNormalizer,
    ) {}

    public function normalize(mixed $data): mixed
    {
        if (is_object($data) && method_exists($data, '__serialize')) {
            return $data->__serialize();
        }

        if ($data instanceof \JsonSerializable) {
            return $data->jsonSerialize();
        }

        return match (true) {
            $data instanceof \BackedEnum => $this->enumNormalizer->normalize($data),
            $data instanceof DateTime, $data instanceof \DateTimeInterface => $this->dateTimeNormalizer->normalize($data),
            $data instanceof Date => $this->dateNormalizer->normalize($data),
            default => $this->normalizeObjectProperties($data),
        };
    }

    private function normalizeObjectProperties(object $data): array
    {
        $properties = get_object_vars($data);
        $filteredProperties = [];

        foreach ($properties as $property => $value) {
            if (null === $value && $this->shouldSkipNull($data, $property)) {
                continue;
            }
            $filteredProperties[$property] = $value;
        }

        return $this->normalizeArray($filteredProperties);
    }

    private function normalizeArray(array $array): array
    {
        return array_map(function($item) {
            return match (true) {
                is_object($item) => $this->normalize($item),
                is_array($item) => $this->normalizeArray($item),
                default => $this->scalarNormalizer->normalize($item),
            };
        }, $array);
    }

    private function shouldSkipNull(object $object, string $property): bool
    {
        try {
            $reflection = new \ReflectionProperty($object, $property);
            $attributes = $reflection->getAttributes(SkipWhenNull::class);

            return [] !== $attributes;
        } catch (\ReflectionException) {
            return false;
        }
    }
}
