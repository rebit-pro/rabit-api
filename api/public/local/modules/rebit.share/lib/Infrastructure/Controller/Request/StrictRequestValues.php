<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Rebit\Share\Infrastructure\Dto\Metadata\DtoMetadataService;
use Rebit\Share\Infrastructure\Dto\Metadata\DtoParameterMetadata;
use Rebit\Share\Infrastructure\Dto\Metadata\DtoParamTypeEnum;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет wire-типы и вложенную форму запроса до допускающей приведение типов гидрации DTO. */
final class StrictRequestValues
{
    /**
     * @param array<string, mixed> $values
     * @param class-string         $className
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $values, string $className, bool $json): array
    {
        $metadata = DtoMetadataService::analyze($className);
        if ([] !== array_diff_key($values, $metadata->allowedKeys)) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }

        foreach ($metadata->parameters as $name => $parameter) {
            if (!array_key_exists($name, $values)) {
                if (!$parameter->hasDefault) {
                    throw new HttpException('UNKNOWN_FIELD', 422);
                }
                continue;
            }
            $values[$name] = self::value($values[$name], $parameter, $json);
        }

        return $values;
    }

    private static function value(mixed $value, DtoParameterMetadata $parameter, bool $json): mixed
    {
        if (null === $value && $parameter->nullable) {
            return null;
        }

        $valid = match ($parameter->type) {
            DtoParamTypeEnum::STRING => is_string($value),
            DtoParamTypeEnum::INT => is_int($value) || (!$json && is_string($value) && 1 === preg_match('/^(?:0|[1-9][0-9]*)$/D', $value)),
            DtoParamTypeEnum::FLOAT => is_float($value) || is_int($value),
            DtoParamTypeEnum::BOOL => is_bool($value),
            DtoParamTypeEnum::OBJECT => $value instanceof \stdClass,
            DtoParamTypeEnum::OBJECT_ARRAY, DtoParamTypeEnum::SCALAR_ARRAY, DtoParamTypeEnum::ARRAY => is_array($value) && array_is_list($value),
            DtoParamTypeEnum::ENUM => is_string($value) || is_int($value),
        };
        if (!$valid) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        if (DtoParamTypeEnum::OBJECT === $parameter->type) {
            return self::object($value, $parameter->className, $json);
        }
        if (DtoParamTypeEnum::OBJECT_ARRAY === $parameter->type) {
            foreach ($value as $index => $item) {
                $value[$index] = self::object($item, $parameter->className, $json);
            }
        }
        if (DtoParamTypeEnum::SCALAR_ARRAY === $parameter->type) {
            $itemParameter = new DtoParameterMetadata(
                type: DtoParamTypeEnum::from($parameter->className),
                nullable: false,
                hasDefault: false,
                default: null,
                className: null,
            );
            foreach ($value as $index => $item) {
                $value[$index] = self::value($item, $itemParameter, $json);
            }
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function object(mixed $value, ?string $className, bool $json): array
    {
        if (!$value instanceof \stdClass || null === $className || !class_exists($className)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return self::normalize(get_object_vars($value), $className, $json);
    }
}
