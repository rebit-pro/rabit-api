<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Helper;

final readonly class StringHelper
{
    /**
     * Преобразует строку вида '-1, 3.000,4, 5' в массив [-1,3,4,5]
     * Пробелы удаляются, пустая строка возвращает пустой массив, не должно быть запятой в конце, не целые значения в списке вызывают исключения.
     * Регулярки работаю примерно в 2 раза быстрее explode + trim.
     *
     * @return int[]
     */
    public static function stringToIntArray(string $string): array
    {
        if ('' === $string) {
            return [];
        }

        // Разрешаем целые числа и дробные только с нулями после точки
        if (1 !== preg_match('/^-?\d+(?:\.0+)?(?:\s*,\s*-?\d+(?:\.0+)?)*$/', $string)) {
            throw new \InvalidArgumentException('В списке есть не только целые числа.');
        }

        return array_map(
            static fn(string $value): int => (int)$value,
            preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY),
        );
    }
}
