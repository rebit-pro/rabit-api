<?php

declare(strict_types=1);

namespace Rebit\Share\Presentation\Command\Attribute;

/**
 * Команда, помеченная этим атрибутом, не допускает параллельный запуск.
 * Перед вызовом handle() захватывается flock. Если лок уже занят —
 * команда завершается с предупреждением и кодом SUCCESS.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class WithLock
{
    public function __construct(
        public ?string $lockName = null,
    ) {}
}
