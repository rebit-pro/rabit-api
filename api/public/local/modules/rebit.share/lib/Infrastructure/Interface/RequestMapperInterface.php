<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Interface;

/**
 * Интерфейс для автомапперов параметров запроса
 *
 * @template T
 */
interface RequestMapperInterface
{
    /**
     * Проверяет поддерживает ли автомаппер указанный класс
     *
     * @param class-string<T> $className
     */
    public function support(string $className): bool;

    /**
     * Создает объект и заполняет его данными
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    public function map(string $className): object;
}
