<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Interface;

/**
 * Интерфейс для нормалайзеров DTO
 */
interface NormalizerInterface
{
    public function normalize(mixed $data): mixed;
}
