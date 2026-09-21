<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Enum;

enum LogChannelEnum: string
{
    case default = 'rebit';
    case access = 'access';
    case commerce = 'commerce';
    case handoff = 'handoff';
    case media = 'media';
    case organization = 'organization';
    case notification = 'notification';
    case leadhunter = 'leadhunter';
    case security = 'security';
    case auth = 'auth';
    case cli = 'cli';
    case todo = 'todo'; // канал для оценки, временного сбора информации и т.п.
    case payment = 'payment';
    case import = 'import'; // канал импортов

    /**
     * Определяет канал логирования по namespace класса.
     *
     * Извлекает второй сегмент из namespace (Rebit\{Module}\...)
     * и пытается найти соответствующий enum-кейс.
     *
     * @param class-string $className FQCN контроллера
     */
    public static function resolveFromClassName(string $className): self
    {
        if (1 === preg_match('/^(?:Rebit|Morefoto)\\\([A-Za-z][A-Za-z0-9_]*)\\\/', $className, $matches)) {
            $resolved = self::tryFrom(strtolower($matches[1]));

            if (null !== $resolved) {
                return $resolved;
            }
        }

        return self::default;
    }
}
