<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Interface;

/**
 * Интерфейс для сериализаторов
 */
interface SerializerInterface
{
    public function serialize(mixed $data): string;
}
