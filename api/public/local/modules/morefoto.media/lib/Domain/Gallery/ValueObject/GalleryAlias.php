<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Gallery\ValueObject;

/**
 * Короткий непредсказуемый псевдоним ключа галереи для ссылки в родительском чате (F3-DEC-01): 10 символов из 32
 * строчных латинских букв и цифр без похожих 0/o и 1/l — 50 бит случайности, не номер группы.
 */
final readonly class GalleryAlias
{
    public const string ALPHABET = '23456789abcdefghijkmnpqrstuvwxyz';
    public const int LENGTH = 10;
    public const string PATTERN = '/^[2-9a-km-np-z]{10}$/D';

    public static function random(): string
    {
        $alias = '';
        foreach (str_split(random_bytes(self::LENGTH)) as $byte) {
            // 256 is a multiple of 32: the low five bits pick a symbol without bias.
            $alias .= self::ALPHABET[ord($byte) & 31];
        }

        return $alias;
    }

    public static function matches(string $value): bool
    {
        return 1 === preg_match(self::PATTERN, $value);
    }
}
