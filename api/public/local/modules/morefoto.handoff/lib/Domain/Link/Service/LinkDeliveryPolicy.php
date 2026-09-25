<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Service;

/**
 * Отсекает невозможные даты передачи ссылки: родители не могли получить ссылку раньше, чем она была выдана.
 * Сотрудник вводит время с точностью до минуты, поэтому момент выдачи сравнивается с началом его минуты.
 */
final readonly class LinkDeliveryPolicy
{
    public function acceptsSentAt(\DateTimeImmutable $sentAt, \DateTimeImmutable $issuedAt): bool
    {
        return $sentAt >= $issuedAt->setTime((int)$issuedAt->format('H'), (int)$issuedAt->format('i'));
    }
}
