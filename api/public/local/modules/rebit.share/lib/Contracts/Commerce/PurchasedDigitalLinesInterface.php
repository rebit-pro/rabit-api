<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

use Rebit\Share\Contracts\Commerce\Dto\PurchasedDigitalLineOutputDto;

/** Купленные электронные строки для служебного архива; бесплатные права покупателя сюда не входят. */
interface PurchasedDigitalLinesInterface
{
    public const int MAX_LINES = 50_000;

    /**
     * Оплаченные digital/bundle учреждения, подтверждённые не позже $at (UTC), без неразрешённой поздней оплаты и coveredByGift.
     * Срок личной ссылки не ограничивает историю. Доступ к учреждению проверяет потребитель.
     * Превышение MAX_LINES вызывает HttpException DIGITAL_ARCHIVE_TOO_LARGE, без усечения результата.
     *
     * @return list<PurchasedDigitalLineOutputDto>
     */
    public function forInstitution(int $institutionId, \DateTimeImmutable $at): array;
}
