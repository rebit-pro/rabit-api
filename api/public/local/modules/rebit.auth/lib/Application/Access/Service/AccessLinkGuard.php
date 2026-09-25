<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Service;

use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Отличает для владельца ссылки «не найдена», «уже использована» и «истекла», чтобы экран подсказал верный шаг.
 * Ссылка другого назначения считается ненайденной: токен сброса не открывает приглашение и наоборот.
 */
final readonly class AccessLinkGuard
{
    /**
     * @throws HttpException
     */
    public function usable(?AccessLink $link, AccessLinkPurposeEnum $purpose, int $now): AccessLink
    {
        if (null === $link || $purpose !== $link->purpose) {
            throw new HttpException('LINK_NOT_FOUND', 404);
        }
        if ($link->isUsed()) {
            throw new HttpException('LINK_USED', 410);
        }
        if ($link->isExpired($now)) {
            throw new HttpException('LINK_EXPIRED', 410);
        }

        return $link;
    }
}
