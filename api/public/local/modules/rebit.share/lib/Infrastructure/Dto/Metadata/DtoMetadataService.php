<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Dto\Metadata;

use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Facade\Cache;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * Метаданные DTO через ReflectionClass.
 * Быстрая гидрация DTO с поддержкой вложенных объектов, минуя Symfony Serializer для повышения производительности х30-400.
 *
 * Кеширование через Cache::rememberArrayValue (директория "dto_metadata") с автосбросом при деплое.
 *
 * ВНИМАНИЕ: горячий код.
 *
 * @internal Используется только через ArrayToDtoMapper
 *
 * @template T of object
 */
final class DtoMetadataService
{
    private const int METADATA_TTL = 2628000;

    /** @var array<class-string, DtoClassMetadata> */
    private static array $cache = [];

    /**
     * Кеш use-стейтментов файлов для резолва коротких имён классов из @ var.
     *
     * @var array<string, array<string, class-string>>
     */
    private static array $useMapCache = [];

    /** @var array<string, true> */
    private const array SCALAR_TYPES = [
        'int' => true,
        'float' => true,
        'string' => true,
        'bool' => true,
        'array' => true,
    ];

    // region API

    /**
     * Отработка аттрибута Symfony для подмены названий полей при сериализации.
     *
     * @see SerializedName
     *
     * @param array<string, mixed> $data
     * @param class-string         $className
     *
     * @return array<string, mixed>
     *
     * @throws ValidationHttpException
     */
    public static function renameSerializedFields(array $data, string $className): array
    {
        $serializedMap = self::analyze($className)->serializedMap;

        if ([] === $serializedMap) {
            return $data;
        }

        foreach ($serializedMap as $external => $internal) {
            if ($external === $internal) {
                continue;
            }

            if (array_key_exists($external, $data)) {
                $data[$internal] = $data[$external];
                unset($data[$external]);
            }
        }

        return $data;
    }

    public static function hasConstraints(string $className): bool
    {
        return self::analyze($className)->hasConstraints;
    }

    /**
     * Допустимые ключи конструктора для фильтрации лишних полей.
     * Формат array<string, true> — для O(1) поиска через array_diff_key/array_intersect_key.
     *
     * @param class-string $className
     *
     * @return array<string, true>
     *
     * @throws ValidationHttpException
     */
    public static function getAllowedKeys(string $className): array
    {
        return self::analyze($className)->allowedKeys;
    }

    /**
     * Гидрация одного DTO.
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $className
     *
     * @return T
     *
     * @throws ValidationHttpException
     */
    public static function hydrate(array $data, string $className): object
    {
        return self::doHydrate($data, $className, self::analyze($className)->parameters);
    }

    /**
     * Гидрация списка DTO. Метаданные извлекаются один раз на весь список.
     *
     * @param array<int, array<string, mixed>> $data
     * @param class-string<T>                  $className
     *
     * @return T[]
     *
     * @throws ValidationHttpException
     */
    public static function hydrateList(array $data, string $className): array
    {
        $parameters = self::analyze($className)->parameters;

        $items = [];
        foreach ($data as $item) {
            $items[] = self::doHydrate($item, $className, $parameters);
        }

        return $items;
    }

    // endregion

    // region Гидрация

    /**
     * Выполняет гидрацию по готовым метаданным: приведение типов, enum, вложенные объекты, nullable, defaults.
     *
     * @param array<string, mixed>                $data
     * @param class-string<T>                     $className
     * @param array<string, DtoParameterMetadata> $parameters
     *
     * @return T
     *
     * @throws ValidationHttpException
     */
    private static function doHydrate(array $data, string $className, array $parameters): object
    {
        $args = [];

        foreach ($parameters as $name => $param) {
            if (!array_key_exists($name, $data)) {
                if ($param->hasDefault) {
                    $args[] = $param->default;
                    continue;
                }

                if ($param->nullable) {
                    $args[] = null;
                    continue;
                }

                throw new ValidationHttpException('В запросе не были переданы поля: ' . $name);
            }

            $value = $data[$name];

            if (null === $value) {
                if ($param->nullable) {
                    $args[] = null;
                    continue;
                }

                throw new ValidationHttpException('В запросе не были переданы поля: ' . $name);
            }

            $args[] = match ($param->type) {
                DtoParamTypeEnum::INT => self::castInt($value, $name),
                DtoParamTypeEnum::FLOAT => self::castFloat($value, $name),
                DtoParamTypeEnum::STRING => self::castString($value, $name),
                DtoParamTypeEnum::BOOL => self::castBool($value, $name),
                DtoParamTypeEnum::ENUM => self::hydrateEnum($value, $param->className),
                DtoParamTypeEnum::OBJECT => self::hydrateObject($value, $param->className),
                DtoParamTypeEnum::OBJECT_ARRAY => self::hydrateObjectArray($value, $param->className),
                DtoParamTypeEnum::SCALAR_ARRAY => self::hydrateScalarArray($value, $name, $param->className),
                DtoParamTypeEnum::ARRAY => $value,
            };
        }

        /** @var T */
        return new $className(...$args);
    }

    // endregion

    // region Приведение типов

    /**
     * @throws ValidationHttpException
     */
    private static function castInt(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return (int)$value;
        }

        throw new ValidationHttpException(
            sprintf('Поле "%s" должно быть целым числом, получен %s', $name, get_debug_type($value)),
        );
    }

    /**
     * @throws ValidationHttpException
     */
    private static function castFloat(mixed $value, string $name): float
    {
        if (is_float($value) || is_int($value)) {
            return (float)$value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float)$value;
        }

        throw new ValidationHttpException(
            sprintf('Поле "%s" должно быть числом, получен %s', $name, get_debug_type($value)),
        );
    }

    /**
     * @throws ValidationHttpException
     */
    private static function castString(mixed $value, string $name): string
    {
        if (is_scalar($value)) {
            return (string)$value;
        }

        throw new ValidationHttpException(
            sprintf('Поле "%s" должно быть строкой, получен %s', $name, get_debug_type($value)),
        );
    }

    /**
     * @throws ValidationHttpException
     */
    private static function castBool(mixed $value, string $name): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            return in_array($value, [1, '1', 'true'], true);
        }

        throw new ValidationHttpException(
            sprintf('Поле "%s" должно быть булевым значением, получен %s', $name, get_debug_type($value)),
        );
    }

    // endregion

    // region Гидрация вложенных типов

    /**
     * @param class-string<\BackedEnum> $enumClass
     *
     * @throws ValidationHttpException
     */
    private static function hydrateEnum(mixed $value, string $enumClass): \BackedEnum
    {
        $enumValue = $enumClass::tryFrom($value);
        if (null === $enumValue) {
            throw new ValidationHttpException(
                sprintf('Недопустимое значение "%s" для enum %s', $value, $enumClass),
            );
        }

        return $enumValue;
    }

    /**
     * @param class-string<T> $className
     *
     * @return T
     *
     * @throws ValidationHttpException
     */
    private static function hydrateObject(mixed $value, string $className): object
    {
        if (!is_array($value)) {
            throw new ValidationHttpException(
                sprintf('Поле должно быть объектом для %s, получен %s', $className, get_debug_type($value)),
            );
        }

        // SerializedName + фильтрация лишних ключей для вложенных DTO
        $value = self::renameSerializedFields($value, $className);

        $allowedKeys = self::getAllowedKeys($className);
        if ([] !== $allowedKeys) {
            $value = array_intersect_key($value, $allowedKeys);
        }

        return self::hydrate($value, $className);
    }

    /**
     * Приводит типы элементов скалярного массива (string[], int[], float[], bool[])
     * аналогично castInt/castFloat/castString/castBool для единичных полей.
     *
     * @return array<int, scalar>
     *
     * @throws ValidationHttpException
     */
    private static function hydrateScalarArray(mixed $value, string $fieldName, string $expectedType): array
    {
        if (!is_array($value)) {
            throw new ValidationHttpException(
                sprintf('Поле "%s" должно быть массивом, получен %s', $fieldName, get_debug_type($value)),
            );
        }

        $castFn = match ($expectedType) {
            'int' => self::castInt(...),
            'float' => self::castFloat(...),
            'string' => self::castString(...),
            'bool' => self::castBool(...),
        };

        foreach ($value as $index => $item) {
            $value[$index] = $castFn($item, $fieldName . '[' . $index . ']');
        }

        return $value;
    }

    /**
     * @param class-string $className
     *
     * @return object[]
     *
     * @throws ValidationHttpException
     */
    private static function hydrateObjectArray(mixed $value, string $className): array
    {
        if (!is_array($value)) {
            throw new ValidationHttpException(
                sprintf('Поле должно быть массивом для %s[], получен %s', $className, get_debug_type($value)),
            );
        }

        $result = [];
        foreach ($value as $item) {
            $result[] = self::hydrateObject($item, $className);
        }

        return $result;
    }

    // endregion

    // region Анализ метаданных

    /**
     * Анализирует класс один раз: извлекает все необходимые данные через рефлексию.
     * Результат кешируется — ReflectionClass создаётся только при первом вызове для каждого класса.
     *
     * @param class-string $className
     *
     * @throws ValidationHttpException
     */
    private static function analyze(string $className): DtoClassMetadata
    {
        if (array_key_exists($className, self::$cache)) {
            return self::$cache[$className];
        }

        return self::$cache[$className] = Cache::rememberArrayValue(
            static fn(): DtoClassMetadata => self::buildMetadata($className),
            cacheId: 'dto_metadata',
            key: $className,
            ttl: self::METADATA_TTL,
            clearOnDeploy: true,
        );
    }

    /**
     * @param class-string $className
     *
     * @throws ValidationHttpException
     */
    private static function buildMetadata(string $className): DtoClassMetadata
    {
        if (!class_exists($className)) {
            return DtoClassMetadata::createEmpty();
        }

        $reflectionClass = new \ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();

        if (null === $constructor) {
            return DtoClassMetadata::createEmpty();
        }

        $allowedKeys = [];
        $parameters = [];
        $serializedMap = [];
        $hasConstraints = false;

        foreach ($reflectionClass->getAttributes() as $attr) {
            if (self::isConstraintAttribute($attr)) {
                $hasConstraints = true;
            }
        }

        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getName();
            $allowedKeys[$paramName] = true;

            try {
                $property = $constructor->getDeclaringClass()->getProperty($paramName);
            } catch (\ReflectionException $exception) {
                throw new ValidationHttpException($exception->getMessage(), previous: $exception);
            }
            foreach ($property->getAttributes() as $attr) {
                if (!$hasConstraints) {
                    $hasConstraints = self::isConstraintAttribute($attr);
                }

                if ('Symfony\Component\Serializer\Annotation\SerializedName' === $attr->getName()) {
                    $instance = $attr->newInstance();
                    $externalName = $instance->getSerializedName();

                    $serializedMap[$externalName] = $paramName;
                }
            }

            $type = $param->getType();

            if (!$type instanceof \ReflectionNamedType) {
                throw new ValidationHttpException(
                    sprintf('Параметр "%s" в "%s" имеет union/intersection тип. DTO поддерживают только именованные типы.', $paramName, $className),
                );
            }

            $typeName = $type->getName();
            $resolvedClassName = null;
            $paramType = null;

            if ('array' === $typeName) {
                $arrayDocType = self::getArrayDocType($param);

                if (null === $arrayDocType) {
                    throw new ValidationHttpException(
                        sprintf('Параметр "%s" в "%s" имеет тип array без @var. Укажите @var для типизации.', $paramName, $className),
                    );
                }

                if (!isset(self::SCALAR_TYPES[$arrayDocType])) {
                    $resolvedClassName = self::resolveClassName($arrayDocType, $reflectionClass);
                    $paramType = DtoParamTypeEnum::OBJECT_ARRAY;
                } elseif ('array' !== $arrayDocType) {
                    $resolvedClassName = $arrayDocType;
                    $paramType = DtoParamTypeEnum::SCALAR_ARRAY;
                } else {
                    $paramType = DtoParamTypeEnum::ARRAY;
                }
            } elseif (isset(self::SCALAR_TYPES[$typeName])) {
                $paramType = DtoParamTypeEnum::from($typeName);
            } elseif (is_subclass_of($typeName, \BackedEnum::class)) {
                $resolvedClassName = $typeName;
                $paramType = DtoParamTypeEnum::ENUM;
            } else {
                $resolvedClassName = $typeName;
                $paramType = DtoParamTypeEnum::OBJECT;
            }

            $parameters[$paramName] = new DtoParameterMetadata(
                type: $paramType,
                nullable: $type->allowsNull(),
                hasDefault: $param->isDefaultValueAvailable(),
                default: $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                className: $resolvedClassName,
            );
        }

        return new DtoClassMetadata(
            allowedKeys: $allowedKeys,
            parameters: $parameters,
            serializedMap: $serializedMap,
            hasConstraints: $hasConstraints,
        );
    }

    private static function isConstraintAttribute(\ReflectionAttribute $attr): bool
    {
        return str_starts_with($attr->getName(), 'Symfony\Component\Validator\Constraints\\');
    }

    // endregion

    // region Резолв типов из phpDoc

    /**
     * Извлекает тип элементов массива из phpDoc (@ var Type[] или @ var array<..., Type>).
     * Возвращает имя типа или null если @ var не указан.
     */
    private static function getArrayDocType(\ReflectionParameter $param): ?string
    {
        $doc = (string)$param->getDeclaringFunction()->getDeclaringClass()
            ?->getProperty($param->getName())
            ?->getDocComment()
        ;

        if ('' === $doc) {
            return null;
        }

        // @var Type[] или @var array<..., Type>
        if (preg_match('/@var\s+(\w+)\[]/', $doc, $matches)) {
            return $matches[1];
        }

        if (preg_match('/@var\s+array<[^,]*,\s*(\w+)>/', $doc, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Резолвит короткое имя класса из @ var в FQCN через use-стейтменты файла.
     *
     * @return class-string
     */
    private static function resolveClassName(string $shortName, \ReflectionClass $reflectionClass): string
    {
        if (str_contains($shortName, '\\')) {
            return ltrim($shortName, '\\');
        }

        $fileName = $reflectionClass->getFileName();
        if (false === $fileName) {
            return $shortName;
        }

        $useMap = self::parseUseStatements($fileName);

        if (isset($useMap[$shortName])) {
            return $useMap[$shortName];
        }

        // Класс в том же namespace
        $namespace = $reflectionClass->getNamespaceName();
        if ('' !== $namespace) {
            return $namespace . '\\' . $shortName;
        }

        return $shortName;
    }

    /**
     * Парсит use-стейтменты PHP-файла, возвращает карту [shortName => FQCN].
     * Результат кешируется.
     *
     * @return array<string, class-string>
     */
    private static function parseUseStatements(string $fileName): array
    {
        if (isset(self::$useMapCache[$fileName])) {
            return self::$useMapCache[$fileName];
        }

        $content = file_get_contents($fileName);
        if (false === $content) {
            return self::$useMapCache[$fileName] = [];
        }

        // Парсим только до объявления класса — use внутри замыканий не нужны
        if (preg_match('/\b(?:class|interface|trait|enum)\s/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $content = substr($content, 0, (int)$m[0][1]);
        }

        $useMap = [];
        preg_match_all('/^use\s+([\w\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fqcn = $match[1];
            $alias = $match[2] ?? null;
            $shortName = $alias ?? substr($fqcn, (int)strrpos($fqcn, '\\') + 1);
            $useMap[$shortName] = $fqcn;
        }

        return self::$useMapCache[$fileName] = $useMap;
    }

    // endregion
}
