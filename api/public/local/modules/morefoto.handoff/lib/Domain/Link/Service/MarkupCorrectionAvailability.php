<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Service;

/**
 * Правило D4 для карточки ссылки: исправление разметки можно начать у переданной группы, которая ещё продаёт, не
 * исправляется и не имеет ни одного заказа. Сервер повторяет эти проверки при старте под блокировкой группы.
 */
final readonly class MarkupCorrectionAvailability
{
    public function available(?string $sentAt, string $state, ?string $markupCorrectionAt, bool $hasOrders): bool
    {
        return null !== $sentAt && null === $markupCorrectionAt && !$hasOrders && in_array($state, ['open', 'digital'], true);
    }
}
