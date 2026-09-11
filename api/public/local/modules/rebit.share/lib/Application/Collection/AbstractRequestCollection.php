<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Collection;

use Rebit\Share\Application\Interface\RequestDtoInterface;

/**
 * Базовый класс для коллекций, которые маппятся из корневого JSON-массива.
 * Тело запроса: [{...}, {...}]
 *
 * @template T of RequestDtoInterface
 *
 * @implements \IteratorAggregate<int, T>
 */
abstract readonly class AbstractRequestCollection implements \IteratorAggregate, \Countable
{
    /**
     * @param T[] $items
     */
    public function __construct(
        private array $items,
    ) {}

    /**
     * @return class-string<T>
     */
    abstract public static function getItemClass(): string;

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return T[]
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
