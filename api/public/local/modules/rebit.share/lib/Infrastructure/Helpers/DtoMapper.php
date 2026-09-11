<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Helpers;

use Rebit\Share\Shared\Exception\RebitException;

/**
 * Маппер для преобразования DTO из внешних сервисов в DTO контрактов.
 * Рекурсивно преобразует все вложенные объекты и массивы.
 *
 * ВНИМАНИЕ: хрупкий разбор аннотаций. Не использовать в горячих местах.
 */
final class DtoMapper
{
    /**
     * @template T of object
     *
     * @param class-string<T> $targetClass
     *
     * @throws RebitException|\ReflectionException
     */
    public static function map(object $source, string $targetClass): object
    {
        $reflection = new \ReflectionClass($targetClass);
        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            throw new RebitException("Класс {$targetClass} не имеет конструктора");
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $args[] = self::mapParameter($source, $parameter);
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @throws RebitException|\ReflectionException
     */
    private static function mapParameter(object $source, \ReflectionParameter $parameter): mixed
    {
        $paramName = $parameter->getName();
        $value = self::extractValue($source, $paramName);

        if (null === $value && $parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $type = $parameter->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        if ('array' === $type->getName()) {
            return self::mapArray($value, $parameter);
        }

        if (!$type->isBuiltin()) {
            return self::mapObject($value, $type->getName());
        }

        return $value;
    }

    private static function extractValue(object $source, string $property): mixed
    {
        if (property_exists($source, $property)) {
            return $source->{$property};
        }

        $getter = 'get' . ucfirst($property);
        if (method_exists($source, $getter)) {
            return $source->{$getter}();
        }

        return null;
    }

    /**
     * @throws RebitException|\ReflectionException
     */
    private static function mapArray(mixed $value, \ReflectionParameter $parameter): array
    {
        if (!is_array($value)) {
            return [];
        }

        $elementClass = self::getArrayElementClass($parameter);
        if (null === $elementClass) {
            return $value;
        }

        return array_map(function($item) use ($elementClass) {
            return self::mapObject($item, $elementClass);
        }, $value);
    }

    /**
     * @throws RebitException|\ReflectionException
     */
    private static function mapObject(mixed $value, string $targetClass): mixed
    {
        if (null === $value) {
            return null;
        }

        if (!is_object($value)) {
            return $value;
        }

        if ($value instanceof $targetClass) {
            return $value;
        }

        return self::map($value, $targetClass);
    }

    private static function getArrayElementClass(\ReflectionParameter $parameter): ?string
    {
        $docComment = $parameter->getDeclaringFunction()->getDocComment();
        if (false === $docComment) {
            return null;
        }

        $paramName = $parameter->getName();
        $pattern = '/@param\s+([^\s]+)\s+\$' . preg_quote($paramName, '/') . '/';
        if (!preg_match($pattern, $docComment, $matches)) {
            return null;
        }

        $typeHint = $matches[1];

        // Формат: array<int, ClassName> или ClassName[]
        if (preg_match('/array<[^,]+,\s*\\\?([A-Za-z0-9_\\\]+)>/', $typeHint, $classMatch)) {
            return '\\' . ltrim($classMatch[1], '\\');
        }

        if (preg_match('/\\\?([A-Za-z0-9_\\\]+)\[\]/', $typeHint, $classMatch)) {
            $className = $classMatch[1];
            if (!str_contains($className, '\\')) {
                $namespace = $parameter->getDeclaringClass()?->getNamespaceName();
                if (null !== $namespace) {
                    return $namespace . '\\' . $className;
                }
            }

            return '\\' . ltrim($className, '\\');
        }

        return null;
    }
}
