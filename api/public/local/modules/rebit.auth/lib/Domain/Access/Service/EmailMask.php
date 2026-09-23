<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\Access\Service;

/**
 * Скрывает адрес на странице приглашения: владелец ссылки узнаёт свой ящик, а посторонний по ссылке — нет.
 * Оставляет первый символ имени ящика и домен целиком.
 */
final readonly class EmailMask
{
    public function mask(string $email): string
    {
        $at = strrpos($email, '@');
        if (false === $at || 0 === $at) {
            return '***';
        }

        return mb_substr($email, 0, 1) . '***' . substr($email, $at);
    }
}
