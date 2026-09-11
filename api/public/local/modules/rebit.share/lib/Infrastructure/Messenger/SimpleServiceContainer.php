<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Psr\Container\ContainerInterface;
use Rebit\Share\Infrastructure\Messenger\Exception\ServiceNotFoundException;

/**
 * Простейший PSR-11 контейнер для локаторов Symfony Messenger.
 *
 * @template T of object
 */
final readonly class SimpleServiceContainer implements ContainerInterface
{
    /**
     * @param array<string, T> $services
     */
    public function __construct(
        private array $services,
    ) {}

    public function get(string $id): object
    {
        if (!$this->has($id)) {
            throw ServiceNotFoundException::forId($id);
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
