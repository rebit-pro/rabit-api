<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger\Exception;

use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Сервис "%s" не найден', $id));
    }
}
