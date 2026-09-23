<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Mapper;

use Morefoto\Access\Application\Avatar\Dto\AvatarOutputDto;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;

/**
 * Строит адреса аватара сотрудника по его версии: версия в адресе делает картинку неизменяемой для кеша браузера,
 * а отсутствие версии означает, что клиент показывает инициалы.
 */
final readonly class AvatarOutputMapper
{
    public function map(int $userId, ?int $version): ?AvatarOutputDto
    {
        if (null === $version || 1 > $version) {
            return null;
        }

        return new AvatarOutputDto(
            version: $version,
            thumbUrl: self::url($userId, AvatarVariantEnum::THUMB, $version),
            fullUrl: self::url($userId, AvatarVariantEnum::FULL, $version),
        );
    }

    private static function url(int $userId, AvatarVariantEnum $variant, int $version): string
    {
        return sprintf('/api/v1/users/%d/avatar/%s?v=%d', $userId, $variant->value, $version);
    }
}
