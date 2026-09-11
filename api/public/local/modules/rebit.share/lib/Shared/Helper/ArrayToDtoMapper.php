<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Helper;

use Rebit\Share\Infrastructure\Dto\Metadata\DtoMetadataService;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Маппит ассоциативный массив на DTO с приведением типов и валидацией.
 * Замена Symfony Serializer с ускорением х30-400 за счёт прямой гидрации через рефлексию.
 *
 * Особенности работы:
 *
 * 1. DTO строится строго по параметрам конструктора — свойства вне конструктора игнорируются.
 *    Union/intersection типы не поддерживаются — только именованные типы.
 *
 * 2. Поддерживаемые типы параметров:
 *    - Скаляры с автоприведением из строк:
 *        - int: принимает int и строку из цифр (с опциональным минусом `/^-?\d+$/`), иначе — исключение.
 *        - float: принимает int, float и числовую строку (is_numeric), иначе — исключение.
 *        - string: принимает любой scalar, приводит к (string), иначе — исключение.
 *        - bool: принимает bool, а также 0/1/'0'/'1'/'true'/'false', иначе — исключение.
 *    - BackedEnum — гидрация через ::tryFrom(), бросает исключение при невалидном значении.
 *    - Вложенные DTO (object) — рекурсивная гидрация с полной обработкой (SerializedName, фильтрация, валидация).
 *    - Типизированные массивы: string[], int[], SomeDto[] — тип определяется ТОЛЬКО из phpDoc над свойством (@ var Type[] или @ var array<..., Type>).
 *
 * 3. Для параметров типа array обязателен phpDoc с @ var, иначе — исключение.
 *    Формат (убрать пробел между @ и var): `@ var Type[]` или `@ var array<key, Type>`.
 *
 * 4. Поддержка Symfony аттрибута #[SerializedName('externalName')] — внешние имена полей переименовываются в имена параметров
 *    конструктора до гидрации (аналог поведения Symfony Serializer).
 *
 * 5. Лишние поля (отсутствующие в конструкторе) молча отбрасываются с warning-логом в канал "todo".
 *    Параметры с default-значениями подставляются автоматически, если поле отсутствует в данных.
 *
 * 6. Nullable параметры: если значение null и тип nullable — подставляется null; если не nullable — исключение.
 *
 * 7. Валидация через Symfony Validator (аннотации/атрибуты) выполняется после гидрации.
 *
 * 8. Кеширование медатанных через Cache::rememberArrayValue (директория "dto_metadata") с автосбросом при деплое.
 *
 * @see DtoMetadataService
 *
 * @template T of object
 */
final class ArrayToDtoMapper
{
    private static ?ValidatorInterface $validator = null;

    /**
     * Дедупликация логов лишних полей: логируем только первый раз для каждого класса.
     *
     * @var array<class-string, true>
     */
    private static array $loggedExtraFields = [];

    // region API

    /**
     * @param array<string, mixed> $data
     * @param class-string<T>      $className
     *
     * @return T
     *
     * @throws ValidationHttpException
     */
    public static function map(array $data, string $className): object
    {
        $data = DtoMetadataService::renameSerializedFields($data, $className);
        $data = self::filterExtraAttributes($data, $className);

        $dto = DtoMetadataService::hydrate($data, $className);
        self::validate($dto);

        return $dto;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @param class-string<T>                  $className
     *
     * @return T[]
     *
     * @throws ValidationHttpException
     */
    public static function mapList(array $data, string $className): array
    {
        foreach ($data as $index => $item) {
            if (!is_array($item)) {
                throw new ValidationHttpException(
                    sprintf('Элемент [%s] должен быть массивом, получен %s', $index, get_debug_type($item)),
                );
            }

            $data[$index] = DtoMetadataService::renameSerializedFields($item, $className);
        }

        $data = self::filterListAttributes($data, $className);

        $items = DtoMetadataService::hydrateList($data, $className);
        self::validateList($items);

        return $items;
    }

    // endregion

    // region Фильтрация лишних полей

    /**
     * @param array<string, mixed> $data
     * @param class-string         $className
     *
     * @return array<string, mixed>
     *
     * @throws ValidationHttpException
     */
    private static function filterExtraAttributes(array $data, string $className): array
    {
        $allowedKeys = DtoMetadataService::getAllowedKeys($className);
        if ([] === $allowedKeys) {
            return $data;
        }

        return self::doFilter($data, $className, $allowedKeys);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @param class-string                     $className
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationHttpException
     */
    private static function filterListAttributes(array $data, string $className): array
    {
        $allowedKeys = DtoMetadataService::getAllowedKeys($className);
        if ([] === $allowedKeys) {
            return $data;
        }

        // allowedKeys извлекается один раз на весь список, а не на каждый элемент
        foreach ($data as $index => $item) {
            $data[$index] = self::doFilter($item, $className, $allowedKeys);
        }

        return $data;
    }

    /**
     * Формат array<string, true> для $allowedKeys — для O(1) поиска через array_diff_key/array_intersect_key.
     *
     * @param array<string, mixed> $data
     * @param class-string         $className
     * @param array<string, true>  $allowedKeys
     *
     * @return array<string, mixed>
     */
    private static function doFilter(array $data, string $className, array $allowedKeys): array
    {
        $extra = array_diff_key($data, $allowedKeys);
        if ([] === $extra) {
            return $data;
        }

        // Дедупликация: логируем лишние поля только при первом вхождении для каждого класса
        if (!isset(self::$loggedExtraFields[$className])) {
            self::$loggedExtraFields[$className] = true;

            Log::channel(LogChannelEnum::todo)->warning(
                sprintf('В запросе переданы лишние поля для DTO (%s): %s', $className, implode(', ', array_keys($extra))),
            );
        }

        return array_intersect_key($data, $allowedKeys);
    }

    // endregion

    // region Валидация

    private static function getValidator(): ValidatorInterface
    {
        return self::$validator ??= Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
        ;
    }

    /**
     * @throws ValidationHttpException
     */
    private static function validate(object $dto): void
    {
        if (!self::hasConstraints($dto::class)) {
            return;
        }

        $errors = self::getValidator()->validate($dto);

        if (count($errors) > 0) {
            $message = '';
            foreach ($errors as $error) {
                $message .= '[' . $error->getPropertyPath() . '] ' . $error->getMessage() . '; ';
            }

            throw new ValidationHttpException($message);
        }
    }

    /**
     * @param object[] $items
     *
     * @throws ValidationHttpException
     */
    private static function validateList(array $items): void
    {
        if ([] === $items || !self::hasConstraints($items[0]::class)) {
            return;
        }

        $validator = self::getValidator();

        foreach ($items as $index => $item) {
            $errors = $validator->validate($item);

            if (count($errors) > 0) {
                $message = '';
                foreach ($errors as $error) {
                    $message .= '[' . $index . '.' . $error->getPropertyPath() . '] ' . $error->getMessage() . '; ';
                }

                throw new ValidationHttpException($message);
            }
        }
    }

    /**
     * @param class-string $className
     */
    private static function hasConstraints(string $className): bool
    {
        return DtoMetadataService::hasConstraints($className);
    }

    // endregion
}
